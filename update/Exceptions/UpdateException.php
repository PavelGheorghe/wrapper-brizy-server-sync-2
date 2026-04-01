<?php

declare(strict_types=1);

class UpdateException extends RuntimeException
{
    /** @var int */
    private $httpStatusCode;
    /** @var string */
    private $responseStatus;

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        string $responseStatus = 'failed'
    ) {
        parent::__construct($message);
        $this->httpStatusCode = $httpStatusCode;
        $this->responseStatus = $responseStatus;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseStatus(): string
    {
        return $this->responseStatus;
    }
}
