<?php
namespace app;

class NgoreiResponse {
    public static function success($data = null, string $message = 'Success'): array {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
    }
    
    public static function error(string $message = 'Error', $data = null): array {
        return [
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ];
    }
    
    public static function json($data, int $code = 200): void {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
} 