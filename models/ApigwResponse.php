<?php

namespace LLReserve;

class ApigwResponse {
    /**
     * @var int
     */
    public $statusCode = 200;
    /**
     * @var object|string
     */
    public $body;

    /**
     * @param object|string $body
     */
    public function __construct($body, int $statusCode = 200) {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    private function isJSON(): bool {
        return !is_string($this->body);
    }

    private function contentType(): string {
        if ($this->isJSON())
            return 'application/json';
        else
            return 'text/plain';
    }

    public function output(): array {
        return [
            'isBase64Encoded' => false,
            'statusCode' => $this->statusCode,
            'headers' => ['Content-Type' => $this->contentType()],
            'body' => $this->isJSON() ? json_encode($this->body) : $this->body
        ];
    }
}
