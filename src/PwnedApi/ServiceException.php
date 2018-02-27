<?php

namespace PwnedApi;

use Throwable;

class ServiceException extends PwnedApiException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct("Error connecting to service.", 1, $previous);
    }
}