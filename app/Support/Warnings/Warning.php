<?php

namespace App\Support\Warnings;

class Warning
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        public string $message,
        public array $keys,
    ) {}

    /**
     * @return array{message: string, keys: list<string>}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'keys' => $this->keys,
        ];
    }
}
