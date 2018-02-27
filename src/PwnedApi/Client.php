<?php declare(strict_types=1);

namespace PwnedApi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;


class Client
{

    /**
     * URL for the password search service
     */
    const PASSWORD_SEARCH_URL = "https://api.pwnedpasswords.com/pwnedpassword/%s";

    /**
     * URL for the range search service
     */
    const RANGE_SEARCH_URL = "https://api.pwnedpasswords.com/range/%s";

    /**
     * Default client options
     *
     * @var array
     */
    private static $defaultOptions = [
        "headers" => [
            "User-Agent"  => "Pwned API for PHP",
            "api-version" => "2"
        ]
    ];

    /**
     * @var ClientInterface
     */
    private $httpClient = null;

    /**
     * Search by hash/password
     *
     * @param string $hash sha1 hash of a password
     *
     * @return PasswordSearchResult
     *
     * @see https://haveibeenpwned.com/API/v2/#SearchingPwnedPasswordsByPassword
     */
    public function searchByPasswordHash(string $hash): PasswordSearchResult
    {
        if (strlen($hash) === 0) {
            throw new \InvalidArgumentException("Argument 1 to " . __METHOD__ . " must be a string with a minimum length of 1.");
        }

        try {
            return $this->doSearchByPassword($hash);
        } catch (BadResponseException $e) {
            if ( ! $e->hasResponse()) {
                throw new ServiceException($e);
            }
            $response = $e->getResponse();
            $code = $response->getStatusCode();

            if ($code === 404) {
                return new PasswordSearchResult(false, 0);
            }
            if ($code === 429) {
                throw new RateLimitException(
                    sprintf("The API is rate limited, please retry after %s seconds.",
                        $response->getHeader("Retry-After")[0]),
                    2,
                    $e
                );
            }

            throw new ServiceException($e);
        } catch (GuzzleException $e) {
            throw new ServiceException($e);
        }
    }

    /**
     * @param string $password
     *
     * @return PasswordSearchResult
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function doSearchByPassword(string $password): PasswordSearchResult
    {
        $url = sprintf(static::PASSWORD_SEARCH_URL, rawurlencode($password));
        $response = $this->getHttpClient()->request("GET", $url, self::$defaultOptions);
        $contents = $response->getBody()->getContents();

        return new PasswordSearchResult(true, (int)$this->stripBom($contents));
    }

    /**
     * @param string $hash
     *
     * @return PasswordSearchResult
     * @see https://haveibeenpwned.com/API/v2/#SearchingPwnedPasswordsByRange
     */
    public function searchByRange(string $hash): PasswordSearchResult
    {
        if (strlen($hash) !== 40) {
            throw new \InvalidArgumentException("Argument 1 to " . __METHOD__ . " must be a sha1 hash with a length of 40.");
        }

        try {
            return $this->doSearchByRange($hash);
        } catch (BadResponseException $e) {
            if ( ! $e->hasResponse()) {
                throw new ServiceException($e);
            }
            $response = $e->getResponse();
            $code = $response->getStatusCode();
            if ($code === 429) {
                throw new RateLimitException(
                    sprintf("The API is rate limited, please retry after %s seconds.",
                        $response->getHeader("Retry-After")[0]),
                    2,
                    $e
                );
            }
            throw new ServiceException($e);
        } catch (GuzzleException $e) {
            throw new ServiceException($e);
        }
    }

    /**
     * @param string $hash
     *
     * @return PasswordSearchResult
     * @throws GuzzleException
     */
    private function doSearchByRange(string $hash): PasswordSearchResult
    {
        $url = sprintf(static::RANGE_SEARCH_URL, strtoupper(rawurlencode(substr($hash, 0, 5))));
        $response = $this->getHttpClient()->request("GET", $url, self::$defaultOptions);
        $contents = $response->getBody()->getContents();

        $regex = sprintf("/%s:(\d+)/", strtoupper(substr($hash, 5)));
        if (preg_match($regex, $this->stripBom($contents), $matches)) {
            $occurrences = (int)$matches[1];
            return new PasswordSearchResult($occurrences > 0, $occurrences);
        }

        return new PasswordSearchResult(false, 0);
    }

    /**
     * @return ClientInterface
     */
    protected function getHttpClient(): ClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient();
        }

        return $this->httpClient;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function stripBom(string $str): string
    {
        $bom = pack("H*", "EFBBBF");

        return preg_replace("/^$bom/", '', $str);
    }

    /**
     * @param ClientInterface $httpClient
     */
    public function setHttpClient(ClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;
    }
}