<?php

namespace App\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    protected string $fromStatus;
    protected string $toStatus;

    public function __construct(string $message = '', string $fromStatus = '', string $toStatus = '')
    {
        parent::__construct($message);
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getDetails(): array
    {
        return [
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ];
    }
}
