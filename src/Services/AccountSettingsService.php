<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;
use App\Repositories\AccountSettingsStoreInterface;
use App\Repositories\RepositoryFactory;

/**
 * Keeps the Amavisd and iRedAPD settings in step with the mail accounts, as
 * Call it after the account itself is deleted or renamed.
 * Only the enabled integrations are touched.
 */
class AccountSettingsService
{
    /** @param string[] $accounts mail addresses of deleted users, aliases or lists */
    public static function deleteAccounts(array $accounts): void
    {
        foreach (self::stores() as $store) {
            $store->deleteAccountSettings($accounts);
        }
    }

    public static function deleteDomain(string $domain): void
    {
        foreach (self::stores() as $store) {
            $store->deleteDomainSettings($domain);
        }
    }

    public static function renameAccount(string $oldAccount, string $newAccount): void
    {
        foreach (self::stores() as $store) {
            $store->renameAccountSettings($oldAccount, $newAccount);
        }
    }

    /**
     * @return AccountSettingsStoreInterface[]
     */
    private static function stores(): array
    {
        $settings = Settings::getInstance();
        $stores = [];
        if ($settings->amavisdEnabled) {
            $stores[] = RepositoryFactory::getAmavisdRepository();
        }
        if ($settings->iredapdEnabled) {
            $stores[] = RepositoryFactory::getIredapdRepository();
        }

        return $stores;
    }
}
