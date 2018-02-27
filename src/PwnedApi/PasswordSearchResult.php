<?php declare(strict_types=1);

namespace PwnedApi;

class PasswordSearchResult
{

    private $found;
    private $count;

    /**
     * PasswordSearchResult constructor.
     *
     * @param $found
     * @param $count
     */
    public function __construct(bool $found, int $count)
    {
        $this->found = $found;
        $this->count = $count;
    }

    /**
     * @return bool
     */
    public function wasFound(): bool
    {
        return $this->found;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

}