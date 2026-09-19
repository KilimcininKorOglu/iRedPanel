<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * A store that keeps per-account settings keyed by the mail address, such as
 * the Amavisd policy and white/blacklist or the iRedAPD throttle and greylisting.
 * Deleting or renaming an account must update these rows too; otherwise a new
 * account with the same address inherits the settings of the old one.
 */
interface AccountSettingsStoreInterface
{
    /** @param string[] $accounts mail addresses */
    public function deleteAccountSettings(array $accounts): void;

    /** Deletes the settings of '@domain', '@.domain' and every address of the domain. */
    public function deleteDomainSettings(string $domain): void;

    /** Moves the settings to the new address and drops the settings the new address had. */
    public function renameAccountSettings(string $oldAccount, string $newAccount): void;
}
