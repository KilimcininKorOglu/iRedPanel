<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\AccountMatch;
use App\Repositories\AmavisdAccountSettings;
use App\Repositories\IredapdAccountSettings;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the cleanup SQL against an in-memory SQLite copy of the Amavisd and
 * iRedAPD tables, so a deleted account leaves no row that a new account with
 * the same address would inherit, and no other account loses a row.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class AccountSettingsTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, policy_id INTEGER)',
            'CREATE TABLE policy (id INTEGER PRIMARY KEY, policy_name TEXT)',
            'CREATE TABLE mailaddr (id INTEGER PRIMARY KEY, email TEXT)',
            'CREATE TABLE wblist (rid INTEGER, sid INTEGER, wb TEXT)',
            'CREATE TABLE outbound_wblist (rid INTEGER, sid INTEGER, wb TEXT)',
            'CREATE TABLE throttle (id INTEGER PRIMARY KEY, account TEXT)',
            'CREATE TABLE throttle_tracking (id INTEGER PRIMARY KEY, tid INTEGER, account TEXT)',
            'CREATE TABLE greylisting (id INTEGER PRIMARY KEY, account TEXT)',
            'CREATE TABLE greylisting_whitelists (id INTEGER PRIMARY KEY, account TEXT)',
            'CREATE TABLE greylisting_whitelist_domain_spf (id INTEGER PRIMARY KEY, account TEXT)',
            'CREATE TABLE greylisting_tracking (id INTEGER PRIMARY KEY, recipient TEXT, rcpt_domain TEXT)',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function testDeleteAccountRemovesOnlyItsAmavisdRows(): void
    {
        $this->seedAmavisd(['a@x.test', 'b@x.test', '@x.test', '@.']);

        (new AmavisdAccountSettings($this->pdo))->delete(AccountMatch::accounts(['a@x.test']));

        $this->assertSame(['@.', '@x.test', 'b@x.test'], $this->column('SELECT email FROM users ORDER BY email'));
        $this->assertSame(['@.', '@x.test', 'b@x.test'], $this->column('SELECT policy_name FROM policy ORDER BY policy_name'));
        $this->assertSame(['@.', '@x.test', 'b@x.test'], $this->wblistOwners('wblist', 'rid'));
        $this->assertSame(['@.', '@x.test', 'b@x.test'], $this->wblistOwners('outbound_wblist', 'sid'));
        // The external addresses stay: other accounts may list them.
        $this->assertCount(4, $this->column('SELECT id FROM mailaddr'));
    }

    public function testDeleteDomainRemovesDomainAndSubdomainRowsOnly(): void
    {
        $this->seedAmavisd(['a@x.test', '@x.test', '@.x.test', 'a@yx.test', '@.', 'x.test@other.test']);

        (new AmavisdAccountSettings($this->pdo))->delete(AccountMatch::domain('x.test'));

        $this->assertSame(['@.', 'a@yx.test', 'x.test@other.test'], $this->column('SELECT email FROM users ORDER BY email'));
        $this->assertSame(['@.', 'a@yx.test', 'x.test@other.test'], $this->column('SELECT policy_name FROM policy ORDER BY policy_name'));
    }

    public function testDomainMatchTreatsLikeWildcardsAsText(): void
    {
        $this->seedAmavisd(['a@x_test', 'a@xytest']);

        (new AmavisdAccountSettings($this->pdo))->delete(AccountMatch::domain('x_test'));

        $this->assertSame(['a@xytest'], $this->column('SELECT email FROM users'));
    }

    public function testDomainMatchRejectsAnEmptyDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AccountMatch::domain('');
    }

    public function testRenameMovesAmavisdRowsAndDropsStaleRowsOfTheNewAddress(): void
    {
        $this->seedAmavisd(['old@x.test', 'new@x.test']);
        $oldId = $this->column("SELECT id FROM users WHERE email = 'old@x.test'")[0];

        (new AmavisdAccountSettings($this->pdo))->rename('old@x.test', 'new@x.test');

        $this->assertSame([$oldId], $this->column("SELECT id FROM users WHERE email = 'new@x.test'"));
        $this->assertSame(['new@x.test'], $this->column('SELECT email FROM users'));
        $this->assertSame(['new@x.test'], $this->column('SELECT policy_name FROM policy'));
        $this->assertSame(['new@x.test'], $this->wblistOwners('wblist', 'rid'));
    }

    public function testDeleteAccountRemovesIredapdRowsAndTracking(): void
    {
        $this->seedIredapd(['a@x.test', 'b@x.test', '@x.test']);

        (new IredapdAccountSettings($this->pdo))->delete(AccountMatch::accounts(['a@x.test']));

        foreach (['throttle', 'greylisting', 'greylisting_whitelists', 'greylisting_whitelist_domain_spf'] as $table) {
            $this->assertSame(['@x.test', 'b@x.test'], $this->column("SELECT account FROM {$table} ORDER BY account"), $table);
        }
        $this->assertSame(['@x.test', 'b@x.test'], $this->column('SELECT account FROM throttle_tracking ORDER BY account'));
        $this->assertSame(['@x.test', 'b@x.test'], $this->column('SELECT recipient FROM greylisting_tracking ORDER BY recipient'));
    }

    public function testDeleteDomainRemovesIredapdRowsOfTheDomain(): void
    {
        $this->seedIredapd(['a@x.test', '@x.test', 'a@y.test', '@.']);

        (new IredapdAccountSettings($this->pdo))->deleteDomain('x.test');

        $this->assertSame(['@.', 'a@y.test'], $this->column('SELECT account FROM throttle ORDER BY account'));
        $this->assertSame(['@.', 'a@y.test'], $this->column('SELECT account FROM throttle_tracking ORDER BY account'));
        $this->assertSame(['@.', 'a@y.test'], $this->column('SELECT recipient FROM greylisting_tracking ORDER BY recipient'));
    }

    public function testRenameMovesIredapdSettingsAndDropsTheCounters(): void
    {
        $this->seedIredapd(['old@x.test', 'new@x.test']);

        (new IredapdAccountSettings($this->pdo))->rename('old@x.test', 'new@x.test');

        $this->assertSame(['new@x.test'], $this->column('SELECT account FROM throttle'));
        $this->assertSame(['new@x.test'], $this->column('SELECT account FROM greylisting'));
        $this->assertSame([], $this->column('SELECT account FROM throttle_tracking'));
        $this->assertSame([], $this->column('SELECT recipient FROM greylisting_tracking'));
    }

    /**
     * Gives every account a users row, a policy, and one inbound and one
     * outbound white/blacklist entry against its own external address.
     *
     * @param string[] $accounts
     */
    private function seedAmavisd(array $accounts): void
    {
        foreach ($accounts as $account) {
            $this->pdo->prepare('INSERT INTO policy (policy_name) VALUES (?)')->execute([$account]);
            $policyId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO users (email, policy_id) VALUES (?, ?)')->execute([$account, $policyId]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO mailaddr (email) VALUES (?)')->execute(['ext-' . $account]);
            $addrId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO wblist (rid, sid, wb) VALUES (?, ?, 'B')")->execute([$userId, $addrId]);
            $this->pdo->prepare("INSERT INTO outbound_wblist (rid, sid, wb) VALUES (?, ?, 'B')")->execute([$addrId, $userId]);
        }
    }

    /** @param string[] $accounts */
    private function seedIredapd(array $accounts): void
    {
        foreach ($accounts as $account) {
            foreach (['throttle', 'greylisting', 'greylisting_whitelists', 'greylisting_whitelist_domain_spf'] as $table) {
                $this->pdo->prepare("INSERT INTO {$table} (account) VALUES (?)")->execute([$account]);
            }
            $tid = $this->column('SELECT MAX(id) FROM throttle')[0];
            $this->pdo->prepare('INSERT INTO throttle_tracking (tid, account) VALUES (?, ?)')->execute([$tid, $account]);
            $domain = ltrim((string) strrchr($account, '@'), '@');
            $this->pdo->prepare('INSERT INTO greylisting_tracking (recipient, rcpt_domain) VALUES (?, ?)')
                ->execute([$account, $domain]);
        }
    }

    /** @return string[] the users.email of the local account behind each remaining entry */
    private function wblistOwners(string $table, string $userColumn): array
    {
        return $this->column("SELECT u.email FROM {$table} w JOIN users u ON u.id = w.{$userColumn} ORDER BY u.email");
    }

    /** @return list<mixed> */
    private function column(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }
}
