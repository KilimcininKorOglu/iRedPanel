<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Settings;
use App\Repositories\Mysql\MysqlAliasRepository;
use App\Repositories\Mysql\MysqlAmavisdRepository;
use App\Repositories\Mysql\MysqlBccRepository;
use App\Repositories\Mysql\MysqlIredapdRepository;
use App\Repositories\Mysql\MysqlDomainOwnershipRepository;
use App\Repositories\Mysql\MysqlLastLoginRepository;
use App\Repositories\Mysql\MysqlSearchRepository;
use App\Repositories\Mysql\MysqlMailingListRepository;
use App\Repositories\Mysql\MysqlSpamPolicyRepository;
use App\Repositories\Mysql\MysqlWhiteBlacklistRepository;
use App\Repositories\Mysql\MysqlRelayRepository;
use App\Repositories\Ldap\LdapAliasRepository;
use App\Repositories\Ldap\LdapBccRepository;
use App\Repositories\Ldap\LdapDashboardRepository;
use App\Repositories\Ldap\LdapSearchRepository;
use App\Repositories\Ldap\LdapMailingListRepository;
use App\Repositories\Ldap\LdapDomainAliasRepository;
use App\Repositories\Ldap\LdapAdminRepository;
use App\Repositories\Ldap\LdapAuthRepository;
use App\Repositories\Ldap\LdapDomainRepository;
use App\Repositories\Ldap\LdapForwardingRepository;
use App\Repositories\Ldap\LdapRelayRepository;
use App\Repositories\Ldap\LdapQuotaRepository;
use App\Repositories\Ldap\LdapUserRepository;
use App\Repositories\Mysql\MysqlDashboardRepository;
use App\Repositories\Mysql\MysqlDomainAliasRepository;
use App\Repositories\Mysql\MysqlAdminRepository;
use App\Repositories\Mysql\MysqlAuthRepository;
use App\Repositories\Mysql\MysqlDomainRepository;
use App\Repositories\Mysql\MysqlForwardingRepository;
use App\Repositories\Mysql\MysqlQuotaRepository;
use App\Repositories\Mysql\MysqlUserRepository;
use App\Repositories\Pgsql\PgsqlDashboardRepository;
use App\Repositories\Pgsql\PgsqlAliasRepository;
use App\Repositories\Pgsql\PgsqlAmavisdRepository;
use App\Repositories\Pgsql\PgsqlBccRepository;
use App\Repositories\Pgsql\PgsqlDomainOwnershipRepository;
use App\Repositories\Pgsql\PgsqlLastLoginRepository;
use App\Repositories\Pgsql\PgsqlSearchRepository;
use App\Repositories\Pgsql\PgsqlMailingListRepository;
use App\Repositories\Pgsql\PgsqlSpamPolicyRepository;
use App\Repositories\Pgsql\PgsqlWhiteBlacklistRepository;
use App\Repositories\Pgsql\PgsqlDomainAliasRepository;
use App\Repositories\Pgsql\PgsqlAdminRepository;
use App\Repositories\Pgsql\PgsqlAuthRepository;
use App\Repositories\Pgsql\PgsqlDomainRepository;
use App\Repositories\Pgsql\PgsqlForwardingRepository;
use App\Repositories\Pgsql\PgsqlIredapdRepository;
use App\Repositories\Pgsql\PgsqlRelayRepository;
use App\Repositories\Pgsql\PgsqlQuotaRepository;
use App\Repositories\Pgsql\PgsqlUserRepository;

/**
 * Returns the correct repository implementation based on IREDPANEL_BACKEND setting.
 */
class RepositoryFactory
{
    private static ?AuthRepositoryInterface $authRepo = null;
    private static ?DomainRepositoryInterface $domainRepo = null;
    private static ?UserRepositoryInterface $userRepo = null;
    private static ?AdminRepositoryInterface $adminRepo = null;
    private static ?ForwardingRepositoryInterface $forwardingRepo = null;
    private static ?QuotaRepositoryInterface $quotaRepo = null;
    private static ?DashboardRepositoryInterface $dashboardRepo = null;
    private static ?DomainAliasRepositoryInterface $domainAliasRepo = null;
    private static ?AliasRepositoryInterface $aliasRepo = null;
    private static ?BccRepositoryInterface $bccRepo = null;
    private static ?RelayRepositoryInterface $relayRepo = null;
    private static ?DomainOwnershipRepositoryInterface $domainOwnershipRepo = null;
    private static ?SearchRepositoryInterface $searchRepo = null;
    private static ?LastLoginRepositoryInterface $lastLoginRepo = null;
    private static ?MailingListRepositoryInterface $mailingListRepo = null;
    private static ?SpamPolicyRepositoryInterface $spamPolicyRepo = null;
    private static ?WhiteBlacklistRepositoryInterface $wblistRepo = null;
    private static ?AmavisdRepositoryInterface $amavisdRepo = null;
    private static ?IredapdRepositoryInterface $iredapdRepo = null;
    private static ?ApiKeyRepositoryInterface $apiKeyRepo = null;
    private static ?DeletedMailboxRepositoryInterface $deletedMailboxRepo = null;
    private static ?PanelSettingsRepositoryInterface $panelSettingsRepo = null;

    public static function getAuthRepository(): AuthRepositoryInterface
    {
        if (self::$authRepo === null) {
            self::$authRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlAuthRepository(),
                'pgsql' => new PgsqlAuthRepository(),
                default => new LdapAuthRepository(),
            };
        }
        return self::$authRepo;
    }

    public static function getDomainRepository(): DomainRepositoryInterface
    {
        if (self::$domainRepo === null) {
            self::$domainRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlDomainRepository(),
                'pgsql' => new PgsqlDomainRepository(),
                default => new LdapDomainRepository(),
            };
        }
        return self::$domainRepo;
    }

    public static function getUserRepository(): UserRepositoryInterface
    {
        if (self::$userRepo === null) {
            self::$userRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlUserRepository(),
                'pgsql' => new PgsqlUserRepository(),
                default => new LdapUserRepository(),
            };
        }
        return self::$userRepo;
    }

    public static function getAdminRepository(): AdminRepositoryInterface
    {
        if (self::$adminRepo === null) {
            self::$adminRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlAdminRepository(),
                'pgsql' => new PgsqlAdminRepository(),
                default => new LdapAdminRepository(),
            };
        }
        return self::$adminRepo;
    }

    public static function getForwardingRepository(): ForwardingRepositoryInterface
    {
        if (self::$forwardingRepo === null) {
            self::$forwardingRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlForwardingRepository(),
                'pgsql' => new PgsqlForwardingRepository(),
                default => new LdapForwardingRepository(),
            };
        }
        return self::$forwardingRepo;
    }

    public static function getQuotaRepository(): QuotaRepositoryInterface
    {
        if (self::$quotaRepo === null) {
            self::$quotaRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlQuotaRepository(),
                'pgsql' => new PgsqlQuotaRepository(),
                default => new LdapQuotaRepository(),
            };
        }
        return self::$quotaRepo;
    }

    public static function getDashboardRepository(): DashboardRepositoryInterface
    {
        if (self::$dashboardRepo === null) {
            self::$dashboardRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlDashboardRepository(),
                'pgsql' => new PgsqlDashboardRepository(),
                default => new LdapDashboardRepository(),
            };
        }
        return self::$dashboardRepo;
    }

    public static function getDomainAliasRepository(): DomainAliasRepositoryInterface
    {
        if (self::$domainAliasRepo === null) {
            self::$domainAliasRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlDomainAliasRepository(),
                'pgsql' => new PgsqlDomainAliasRepository(),
                default => new LdapDomainAliasRepository(),
            };
        }
        return self::$domainAliasRepo;
    }

    public static function getBccRepository(): BccRepositoryInterface
    {
        if (self::$bccRepo === null) {
            self::$bccRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlBccRepository(),
                'pgsql' => new PgsqlBccRepository(),
                default => new LdapBccRepository(),
            };
        }
        return self::$bccRepo;
    }

    public static function getRelayRepository(): RelayRepositoryInterface
    {
        if (self::$relayRepo === null) {
            self::$relayRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlRelayRepository(),
                'pgsql' => new PgsqlRelayRepository(),
                default => new LdapRelayRepository(),
            };
        }
        return self::$relayRepo;
    }

    public static function getDomainOwnershipRepository(): DomainOwnershipRepositoryInterface
    {
        if (self::$domainOwnershipRepo === null) {
            self::$domainOwnershipRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlDomainOwnershipRepository(),
                default => new MysqlDomainOwnershipRepository(),
            };
        }
        return self::$domainOwnershipRepo;
    }

    public static function getSearchRepository(): SearchRepositoryInterface
    {
        if (self::$searchRepo === null) {
            self::$searchRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlSearchRepository(),
                'pgsql' => new PgsqlSearchRepository(),
                default => new LdapSearchRepository(),
            };
        }
        return self::$searchRepo;
    }

    public static function getLastLoginRepository(): LastLoginRepositoryInterface
    {
        if (self::$lastLoginRepo === null) {
            self::$lastLoginRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlLastLoginRepository(),
                default => new MysqlLastLoginRepository(),
            };
        }
        return self::$lastLoginRepo;
    }

    public static function getMailingListRepository(): MailingListRepositoryInterface
    {
        if (self::$mailingListRepo === null) {
            self::$mailingListRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlMailingListRepository(),
                'pgsql' => new PgsqlMailingListRepository(),
                default => new LdapMailingListRepository(),
            };
        }
        return self::$mailingListRepo;
    }

    public static function getSpamPolicyRepository(): SpamPolicyRepositoryInterface
    {
        if (self::$spamPolicyRepo === null) {
            self::$spamPolicyRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlSpamPolicyRepository(),
                default => new MysqlSpamPolicyRepository(),
            };
        }
        return self::$spamPolicyRepo;
    }

    public static function getWhiteBlacklistRepository(): WhiteBlacklistRepositoryInterface
    {
        if (self::$wblistRepo === null) {
            self::$wblistRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlWhiteBlacklistRepository(),
                default => new MysqlWhiteBlacklistRepository(),
            };
        }
        return self::$wblistRepo;
    }

    public static function getAliasRepository(): AliasRepositoryInterface
    {
        if (self::$aliasRepo === null) {
            self::$aliasRepo = match (Settings::getInstance()->backend) {
                'mysql' => new MysqlAliasRepository(),
                'pgsql' => new PgsqlAliasRepository(),
                default => new LdapAliasRepository(),
            };
        }
        return self::$aliasRepo;
    }

    public static function getAmavisdRepository(): AmavisdRepositoryInterface
    {
        if (self::$amavisdRepo === null) {
            self::$amavisdRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlAmavisdRepository(),
                default => new MysqlAmavisdRepository(),
            };
        }
        return self::$amavisdRepo;
    }

    public static function getIredapdRepository(): IredapdRepositoryInterface
    {
        if (self::$iredapdRepo === null) {
            self::$iredapdRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new PgsqlIredapdRepository(),
                default => new MysqlIredapdRepository(),
            };
        }
        return self::$iredapdRepo;
    }

    public static function getApiKeyRepository(): ApiKeyRepositoryInterface
    {
        if (self::$apiKeyRepo === null) {
            self::$apiKeyRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new Pgsql\PgsqlApiKeyRepository(),
                default => new Mysql\MysqlApiKeyRepository(),
            };
        }
        return self::$apiKeyRepo;
    }

    public static function getDeletedMailboxRepository(): DeletedMailboxRepositoryInterface
    {
        if (self::$deletedMailboxRepo === null) {
            self::$deletedMailboxRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new Pgsql\PgsqlDeletedMailboxRepository(),
                default => new Mysql\MysqlDeletedMailboxRepository(),
            };
        }
        return self::$deletedMailboxRepo;
    }

    public static function getPanelSettingsRepository(): PanelSettingsRepositoryInterface
    {
        if (self::$panelSettingsRepo === null) {
            self::$panelSettingsRepo = match (Settings::getInstance()->backend) {
                'pgsql' => new Pgsql\PgsqlPanelSettingsRepository(),
                default => new Mysql\MysqlPanelSettingsRepository(),
            };
        }
        return self::$panelSettingsRepo;
    }
}
