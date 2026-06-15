<?php

namespace App\Exceptions\Marzban;

use RuntimeException;

class MarzbanApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function response(): ?array
    {
        return $this->response;
    }
}
