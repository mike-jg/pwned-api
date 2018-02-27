<?php

namespace PwnedApi\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PwnedApi\Client;
use PwnedApi\PasswordSearchResult;
use PwnedApi\RangeSearchResult;

class ClientTest extends TestCase
{

    /**
     * @var Client
     */
    private $apiClient;

    public function setUp()
    {
        $this->apiClient = new Client();
    }

    public function provideSuccess()
    {
        // test with and without BOM
        return [
            [new Response(200, [], "50"), 50],
            [new Response(200, [], "20312"), 20312],
            [new Response(200, [], "1"), 1],
            [new Response(200, [], "65230"), 65230],
            [new Response(200, [], pack("H*", "EFBBBF") . "65230"), 65230],
            [new Response(200, [], pack("H*", "EFBBBF") . "542"), 542],
        ];
    }

    /**
     * @param $response
     * @param $expect
     *
     * @dataProvider provideSuccess
     */
    public function testSearchByPassword($response, $expect)
    {
        $mock = new MockHandler([
            $response
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $result = $this->apiClient->searchByPasswordHash("test");

        $this->assertInstanceOf(PasswordSearchResult::class, $result);
        $this->assertSame($expect, $result->getCount());
        $this->assertSame(true, $result->wasFound());
    }


    /**
     * @return array
     * @throws \ReflectionException
     */
    public function provideGeneralFailure()
    {
        /** @var $mockRequest RequestInterface */
        $mockRequest = $this->createMock(RequestInterface::class);
        /** @var $mockRequest ResponseInterface */
        $mockResponse = $this->createMock(ResponseInterface::class);

        $mockResponse
            ->method("getStatusCode")
            ->willReturn(401);

        return [
            [new RequestException("msg", $mockRequest)],
            [new BadResponseException("msg", $mockRequest)],
            [new BadResponseException("msg", $mockRequest, $mockResponse)],
            [new TooManyRedirectsException("msg", $mockRequest)],
            [new TransferException()],
            [new ConnectException("msg", $mockRequest)],
        ];
    }

    /**
     * @dataProvider provideGeneralFailure
     * @expectedException \PwnedApi\ServiceException
     *
     * @param $e
     */
    public function testCorrectlyWrapsGeneralFailuresWithServiceException($e)
    {
        $mock = new MockHandler([
            $e
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByPasswordHash("test");
    }

    /**
     * @expectedException \PwnedApi\RateLimitException
     * @expectedExceptionMessage The API is rate limited, please retry after 2 seconds.
     */
    public function testCorrectlyWrapsWithRateLimitException()
    {
        $mock = new MockHandler([
            new Response(429, ["Retry-After" => 2], "")
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByPasswordHash("test");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCorrectlyThowsExceptionForZeroLengthArgument()
    {
        $this->apiClient->searchByPasswordHash("");
    }

    public function testCorrectlyReturnsResultFor404()
    {
        $mock = new MockHandler([
            new Response(404, [], "")
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $result = $this->apiClient->searchByPasswordHash("test");

        $this->assertInstanceOf(PasswordSearchResult::class, $result);
        $this->assertSame(0, $result->getCount());
        $this->assertSame(false, $result->wasFound());
    }

    public function provideUrl()
    {
        return [
            ["test", "https://api.pwnedpasswords.com/pwnedpassword/test"],
            ["test+&/", "https://api.pwnedpasswords.com/pwnedpassword/test%2B%26%2F"],
            ["a test password", "https://api.pwnedpasswords.com/pwnedpassword/a%20test%20password"],
        ];
    }

    /**
     * @dataProvider provideUrl
     *
     * @param $password
     * @param $expectedUrl
     *
     * @throws \ReflectionException
     */
    public function testCorrectlySetsUrl($password, $expectedUrl)
    {
        $guzzle = $this->createMock(HttpClient::class);
        $guzzle->expects($this->once())
               ->method("request")
               ->with("GET", $expectedUrl)
               ->willReturn(new Response(404, [], ""));

        /** @var $guzzle HttpClient */
        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByPasswordHash($password);
    }

    /**
     * @expectedException \PwnedApi\RateLimitException
     * @expectedExceptionMessage The API is rate limited, please retry after 2 seconds.
     */
    public function testCorrectlyWrapsWithRateLimitExceptionForRange()
    {
        $mock = new MockHandler([
            new Response(429, ["Retry-After" => 2], "")
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByRange(sha1("test"));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCorrectlyThrowsExceptionForInvalidLengthArgument()
    {
        $mock = new MockHandler([
            new Response(429, ["Retry-After" => 2], "")
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByRange("test");
    }

    public function provideRangeSuccess()
    {
        // test with and without BOM
        return [
            [
                new Response(200, [], "05E0182DEAE22D02F6ED35280BCAC370179:4"),
                "AAAAA05E0182DEAE22D02F6ED35280BCAC370179",
                4
            ],
            [
                new Response(200, [],
                    "05E0182DEAE22D02F6EDAAA80BCAC370179:4\n05E0182DEAE22D02F6ED35280BCAC370179:50213"),
                "XYAAZ05E0182DEAE22D02F6ED35280BCAC370179",
                50213
            ],
            [
                new Response(200, [],
                    pack("H*", "EFBBBF") . "05E0182DEAE22D02F6EDAAA80BCAC370179:4\n05E0182DEAE22D02F6ED35280BCAC370179:50213"),
                "XYAAZ05E0182DEAE22D02F6ED35280BCAC370179",
                50213
            ],
            [
                new Response(200, [],
                    "05E0182DEAE22D02F6EDAAA80BCAC370179:4\n05E0182DEAE22D02FFFD35280BCAC370179:68\n05E0182DEAE22D02F6ED35280BCAC370179:50213"),
                "ABCDE05E0182DEAE22D02FFFD35280BCAC370179",
                68
            ],
            [
                new Response(200, [],
                    "05E0182DEAE22D02F6EDAAA80BCAC370179:4\n05E0182DEAE22D02FFFD35280BCAC370179:68\n05E0182DEAE22D02F6ED35280BCAC370179:50213"),
                "ABCDE05E0182DEAE22D02FQFD35280BCAC370179",
                0
            ],
        ];
    }

    /**
     * @param $response
     * @param $hash
     * @param $expect
     *
     * @dataProvider provideRangeSuccess
     */
    public function testSearchByRange($response, $hash, $expect)
    {
        $mock = new MockHandler([
            $response
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $result = $this->apiClient->searchByRange($hash);

        $this->assertInstanceOf(PasswordSearchResult::class, $result);
        $this->assertSame($expect, $result->getCount());
        $this->assertSame($expect > 0, $result->wasFound());
    }


    /**
     * @dataProvider provideGeneralFailure
     * @expectedException \PwnedApi\ServiceException
     *
     * @param $e
     */
    public function testRangeSearchCorrectlyWrapsGeneralFailuresWithServiceException($e)
    {
        $mock = new MockHandler([
            $e
        ]);

        $handler = HandlerStack::create($mock);
        $guzzle = new HttpClient(["handler" => $handler]);

        $this->apiClient->setHttpClient($guzzle);

        $this->apiClient->searchByRange(sha1("test"));
    }
}
