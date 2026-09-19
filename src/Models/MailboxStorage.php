<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Repositories\UserRepositoryInterface;
use App\Utils\FormValue;

/**
 * The storage settings of a new mailbox: mailbox format, mailbox folder and an
 * absolute maildir path. A null value uses the
 * backend default (SQL column default, Dovecot default for LDAP) or the panel layout.
 */
final class MailboxStorage
{
    /** The Dovecot mailbox formats that the panel accepts. */
    public const FORMATS = ['maildir', 'mdbox', 'sdbox'];

    /** The iRedMail defaults of the SQL columns mailboxformat and mailboxfolder. */
    public const DEFAULT_FORMAT = 'maildir';
    public const DEFAULT_FOLDER = 'Maildir';

    private const FOLDER_PATTERN = '/^[a-zA-Z0-9]{1,20}$/';

    /** An absolute path of at least four directories, lowercase, without "." or ".." segments. */
    private const PATH_PATTERN = '#^(/(?!\.{1,2}(/|$))[a-z0-9._@+-]+){4,}$#';

    public function __construct(
        public readonly ?string $format = null,
        public readonly ?string $folder = null,
        /** Absolute home directory of the mailbox, without a trailing slash. */
        public readonly ?string $path = null,
    ) {}

    /**
     * Reads `mailboxFormat`, `mailboxFolder` and `maildir` of a form or a JSON body.
     * An empty value uses the default.
     *
     * @throws InvalidInputException when a value is invalid, or when the admin may not set a path
     */
    public static function fromInput(array $data, bool $mayChoosePath): self
    {
        return new self(
            self::validFormat(strtolower(trim(FormValue::text($data, 'mailboxFormat')))),
            self::validFolder(trim(FormValue::text($data, 'mailboxFolder'))),
            self::validPath(rtrim(strtolower(trim(FormValue::text($data, 'maildir'))), '/'), $mayChoosePath),
        );
    }

    private static function validFormat(string $format): ?string
    {
        if ($format === '') {
            return null;
        }
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidInputException('mailboxFormat must be one of ' . implode(', ', self::FORMATS), 'user.msg_invalid_mailbox_format');
        }

        return $format;
    }

    private static function validFolder(string $folder): ?string
    {
        if ($folder === '') {
            return null;
        }
        if (preg_match(self::FOLDER_PATTERN, $folder) !== 1) {
            throw new InvalidInputException('mailboxFolder must be 1 to 20 letters or digits', 'user.msg_invalid_mailbox_folder');
        }

        return $folder;
    }

    private static function validPath(string $path, bool $mayChoosePath): ?string
    {
        if ($path === '') {
            return null;
        }
        if (!$mayChoosePath) {
            throw new InvalidInputException('maildir requires a global admin', 'user.msg_maildir_global_only');
        }
        if (preg_match(self::PATH_PATTERN, $path) !== 1) {
            throw new InvalidInputException('maildir must be an absolute path of at least four directories', 'user.msg_invalid_maildir');
        }

        return $path;
    }

    /**
     * The storage base directory, storage node and maildir (with a trailing slash) of the
     * new mailbox, as the SQL mailbox columns hold them. Without a path the panel layout
     * `<vmail path>/<storage node>/<domain>/<uid>/` applies.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function location(string $domain, string $uid, string $vmailPath, string $storageNode): array
    {
        $base = rtrim($vmailPath, '/');
        if ($this->path === null) {
            return [$base, $storageNode, "{$domain}/{$uid}/"];
        }
        $prefix = "{$base}/{$storageNode}/";
        if (str_starts_with($this->path, $prefix)) {
            return [$base, $storageNode, substr($this->path, strlen($prefix)) . '/'];
        }
        $segments = explode('/', ltrim($this->path, '/'));

        return ['/' . array_shift($segments), (string) array_shift($segments), implode('/', $segments) . '/'];
    }

    /**
     * @throws InvalidInputException when another mailbox lives at the path or in a parent
     *         directory of it, because the new mailbox would then read its mail
     */
    public function assertPathFree(UserRepositoryInterface $users): void
    {
        if ($this->path !== null && $users->isMailboxPathInUse($this->pathAndParents())) {
            throw new InvalidInputException("maildir {$this->path} belongs to another mailbox", 'user.msg_maildir_in_use');
        }
    }

    /**
     * The path and each parent directory, with and without a trailing slash.
     *
     * @return list<string>
     */
    public function pathAndParents(): array
    {
        $paths = [];
        for ($path = (string) $this->path; $path !== '' && $path !== '/'; $path = dirname($path)) {
            array_push($paths, $path, "{$path}/");
        }

        return $paths;
    }
}
