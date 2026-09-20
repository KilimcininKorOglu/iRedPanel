<?php

declare(strict_types=1);

namespace App\Services;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Repositories\RepositoryFactory;
use App\Utils\FormValue;

/**
 * The shared folder page of a mailbox: what Dovecot stored, and the removal of one
 * share. Dovecot reads the rows as the authoritative list, so a removed row ends the
 * share; the panel never adds a row, because only the user shares a folder.
 */
final class MailboxSharing
{
    /** Whether the database that holds the shared folder rows is configured. */
    public static function available(): bool
    {
        return RepositoryFactory::getMailboxSharing() !== null;
    }

    /**
     * The shares of one mailbox, in both directions.
     *
     * @return array<string, mixed> empty when no database holds the rows
     */
    public static function tabData(string $email): array
    {
        $sharing = RepositoryFactory::getMailboxSharing();
        if ($sharing === null) {
            return [];
        }

        return [
            'sharedWith' => $sharing->sharedWith($email),
            'sharedBy' => $sharing->sharedBy($email),
            'sharesWithAnyone' => $sharing->sharesWithAnyone($email),
        ];
    }

    /**
     * Revokes the share that the form names: one account in `toUser`, or the share
     * with every account when the field is empty.
     *
     * @return array{success: string}
     */
    public static function revokePosted(string $domain, string $userUid): array
    {
        CsrfProtection::validateToken();
        $sharing = RepositoryFactory::getMailboxSharing();
        if ($sharing === null) {
            throw new \RuntimeException(Translator::translate('user.msg_sharing_unavailable'));
        }

        $email = "{$userUid}@{$domain}";
        $target = FormValue::text($_POST, 'toUser');
        if ($target === '') {
            $sharing->revokeAnyone($email);
        } else {
            $sharing->revoke($email, $target);
        }
        ActivityLogger::logUpdate($domain, $userUid, 'Shared folder revoked: ' . ($target === '' ? 'anyone' : $target));

        return ['success' => Translator::translate('user.msg_share_revoked')];
    }
}
