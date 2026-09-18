<?php

namespace App\Services\XcVm\Exceptions;

class XcVmApiException extends XcVmException
{
    private string $action = '';

    private ?array $data = null;

    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        ?array $data = null,
    ) {
        parent::__construct($message);
        $this->data = $data;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}