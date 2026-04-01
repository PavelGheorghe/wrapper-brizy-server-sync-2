<?php

declare(strict_types=1);

class JsonResponder
{
    public function send(int $statusCode, array $payload)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
