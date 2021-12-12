<?php

require_once 'vendor/autoload.php';

use LLReserve\ApigwResponse;
use LLReserve\AppResponse;

function success(string $message): array {
    return (new ApigwResponse(new AppResponse(true, $message)))->output();
}

function failed(string $message): array {
    return (new ApigwResponse(new AppResponse(false, $message)))->output();
}

function error(int $code, string $message): array {
    return (new ApigwResponse($message, $code))->output();
}
