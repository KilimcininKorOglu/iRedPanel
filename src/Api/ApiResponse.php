<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\PaginatedResult;

class ApiResponse
{
    public static function success(array $data, int $status = 200): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            self::error('Response serialization failed', 500);
            return;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo $json;
    }

    public static function error(string $message, int $status = 400): void
    {
        $json = json_encode(['error' => $message], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"error":"Response serialization failed"}';
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo $json;
    }

    public static function paginated(PaginatedResult $result, ?callable $transform = null): void
    {
        $items = [];
        foreach ($result->items as $item) {
            $items[] = $transform !== null ? $transform($item) : (array) $item;
        }

        self::success([
            'items' => $items,
            'total' => $result->totalCount,
            'page' => $result->currentPage,
            'perPage' => $result->perPage,
            'totalPages' => $result->totalPages,
        ]);
    }

    public static function deleted(): void
    {
        self::success(['message' => 'Deleted successfully']);
    }

    public static function created(array $data = []): void
    {
        self::success(array_merge(['message' => 'Created successfully'], $data), 201);
    }
}
