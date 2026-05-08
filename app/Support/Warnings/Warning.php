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
        public ?string $framework = null,
    ) {}

    /**
     * @return array{message: string, keys: list<string>, framework: ?string}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'keys' => $this->keys,
            'framework' => $this->framework,
        ];
    }
}
