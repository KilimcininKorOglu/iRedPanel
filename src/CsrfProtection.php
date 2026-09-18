<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\CsrfTokenException;

class CsrfProtection
{
    /**
     * Generates a CSRF token and stores it in the session.
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrfToken'];
    }

    /**
     * Validates the CSRF token from the POST request.
     *
     * @throws CsrfTokenException if the token is missing or invalid
     */
    public static function validateToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['_csrf_token'] ?? '';
        $expected = $_SESSION['csrfToken'] ?? '';

        if (!is_string($token) || $token === '' || $expected === '' || !hash_equals($expected, $token)) {
            throw new CsrfTokenException('Invalid CSRF token');
        }
    }
}
