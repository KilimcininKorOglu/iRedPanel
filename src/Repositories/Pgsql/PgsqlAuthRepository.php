<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\AuthRepositoryInterface;
use App\Utils\PasswordVerifier;

class PgsqlAuthRepository implements AuthRepositoryInterface
{
    public function authenticate(string $email, string $password): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // Try standalone admin table first
        $stmt = $pdo->prepare('SELECT password FROM admin WHERE username = :username AND active = 1');
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();

        if ($row !== false) {
            if (!self::verifyPassword($password, $row['password'])) {
                throw new \Exception("Invalid password for {$email}");
            }

            // Check if admin has any domain assignments
            $stmt = $pdo->prepare("SELECT 1 FROM domain_admins WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $email]);
            if ($stmt->fetch() === false) {
                throw new \Exception("User {$email} has no admin privileges");
            }

            return true;
        }

        // Try mailbox-based admin
        $stmt = $pdo->prepare(
            'SELECT password FROM mailbox WHERE username = :username AND active = 1 AND (isadmin = 1 OR isglobaladmin = 1)'
        );
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new \Exception("Admin user {$email} not found or inactive");
        }

        if (!self::verifyPassword($password, $row['password'])) {
            throw new \Exception("Invalid password for {$email}");
        }

        return true;
    }

    public function isGlobalAdmin(string $email): bool
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // Check domain_admins table for 'ALL'
        $stmt = $pdo->prepare("SELECT 1 FROM domain_admins WHERE username = :username AND domain = 'ALL' LIMIT 1");
        $stmt->execute(['username' => $email]);
        if ($stmt->fetch() !== false) {
            return true;
        }

        // Check mailbox table for isglobaladmin flag
        $stmt = $pdo->prepare("SELECT 1 FROM mailbox WHERE username = :username AND isglobaladmin = 1 LIMIT 1");
        $stmt->execute(['username' => $email]);
        return $stmt->fetch() !== false;
    }

    public function getManagedDomains(string $email): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT domain FROM domain_admins WHERE username = :username AND domain != 'ALL' ORDER BY domain"
        );
        $stmt->execute(['username' => $email]);

        $domains = [];
        while ($row = $stmt->fetch()) {
            $domains[] = $row['domain'];
        }

        return $domains;
    }

    public function getLanguage(string $email): string
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare('SELECT language FROM admin WHERE username = :username');
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();
        if ($row !== false && !empty($row['language'])) {
            return (string) $row['language'];
        }

        // Mailbox-based admin fallback.
        $stmt = $pdo->prepare('SELECT language FROM mailbox WHERE username = :username');
        $stmt->execute(['username' => $email]);
        $row = $stmt->fetch();
        if ($row !== false && !empty($row['language'])) {
            return (string) $row['language'];
        }

        return '';
    }

    public function setLanguage(string $email, string $locale): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare('UPDATE admin SET language = :language WHERE username = :username');
        $stmt->execute(['language' => $locale, 'username' => $email]);
        if ($stmt->rowCount() > 0) {
            return;
        }

        // Fall back to the mailbox-based admin record.
        $stmt = $pdo->prepare('UPDATE mailbox SET language = :language WHERE username = :username');
        $stmt->execute(['language' => $locale, 'username' => $email]);
    }

    public function supportsLanguagePersistence(): bool
    {
        return true;
    }

    private static function verifyPassword(string $password, string $storedHash): bool
    {
        return PasswordVerifier::verify($password, $storedHash);
    }
}
