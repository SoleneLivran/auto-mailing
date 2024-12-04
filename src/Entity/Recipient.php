<?php

namespace App\Entity;

readonly class Recipient
{
    public function __construct(
        private string $firstName,
        private string $email,
    ) {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
