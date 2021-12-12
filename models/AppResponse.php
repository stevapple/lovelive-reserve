<?php

namespace LLReserve;

class AppResponse {
    /**
     * @var bool
     */
    public $success;
    /**
     * @var string
     */
    public $message;

    public function __construct(bool $success, string $message) {
        $this->success = $success;
        $this->message = $message;
    }
}
