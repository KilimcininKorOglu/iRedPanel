<?php

declare(strict_types=1);

namespace Tests\Services\Replication;

use App\Models\ReplicatedAccount;
use App\Services\Replication\ReplicationAction;
use App\Services\Replication\ReplicationPlan;
use App\Services\Replication\ReplicationPlanner;
use App\Services\Replication\SourceAccount;
use PHPUnit\Framework\TestCase;

class ReplicationPlannerTest extends TestCase
{
    /** @var string[] addresses of local accounts */
    private array $local = [];

    private function planner(bool $allowMassDisable = false): ReplicationPlanner
    {
        return new ReplicationPlanner(fn(string $address): bool => in_array($address, $this->local, true), $allowMassDisable);
    }

    private static function user(string $guid, string $address, array $profile = ['cn' => 'Name'], ?bool $active = true): SourceAccount
    {
        return new SourceAccount($guid, ReplicatedAccount::KIND_USER, "CN={$guid},DC=x", $address, '', $active, $profile);
    }

    private static function group(string $guid, string $address, array $memberDns): SourceAccount
    {
        return new SourceAccount($guid, ReplicatedAccount::KIND_GROUP, "CN={$guid},DC=x", $address, name: $guid, memberDns: $memberDns);
    }

    /**
     * Plans once, stores the links as the runner would, and returns the actions.
     *
     * @param array<string, ReplicatedAccount> $links
     */
    private static function linksAfter(ReplicationPlan $plan, array $links): array
    {
        foreach ($plan->actions as $action) {
            if ($action->type === ReplicationAction::FORGET) {
                unset($links[$action->guid]);
                continue;
            }
            $state = match ($action->type) {
                ReplicationAction::DISABLE => ReplicatedAccount::STATE_MISSING,
                ReplicationAction::CONFLICT => $action->link?->ownsAccount() ? $action->link->state : ReplicatedAccount::STATE_CONFLICT,
                ReplicationAction::SKIP => ReplicatedAccount::STATE_SKIPPED,
                default => ReplicatedAccount::STATE_ACTIVE,
            };
            $address = $action->type === ReplicationAction::CONFLICT && $action->link?->ownsAccount() ? $action->link->address : $action->address();
            $links[$action->guid] = new ReplicatedAccount($action->guid, $action->kind, $address, '', $action->fingerprint, $state, 1);
        }

        return $links;
    }

    /** @return array<string, string> guid => action type */
    private static function types(ReplicationPlan $plan): array
    {
        $types = [];
        foreach ($plan->actions as $action) {
            $types[$action->guid] = $action->type;
        }
        ksort($types);

        return $types;
    }

    public function testNewObjectsAreCreatedOnceAndUnchangedOnesAreLeftAlone(): void
    {
        $users = [self::user('g1', 'a@x.test'), self::user('g2', 'b@x.test')];
        $plan = $this->planner()->plan($users, null, []);
        $this->assertSame(['g1' => 'create', 'g2' => 'create'], self::types($plan));

        $this->assertSame([], $this->planner()->plan($users, null, self::linksAfter($plan, []))->actions);
    }

    public function testAChangedProfileOrStatusIsUpdated(): void
    {
        $links = self::linksAfter($this->planner()->plan([self::user('g1', 'a@x.test')], null, []), []);

        $renamed = $this->planner()->plan([self::user('g1', 'a@x.test', ['cn' => 'New'])], null, $links);
        $this->assertSame(['g1' => 'update'], self::types($renamed));

        $disabled = $this->planner()->plan([self::user('g1', 'a@x.test', active: false)], null, $links);
        $this->assertSame(['g1' => 'update'], self::types($disabled));
    }

    public function testALocalAccountWithTheAddressIsAConflictThatIsLoggedOnce(): void
    {
        $this->local = ['a@x.test'];
        $users = [self::user('g1', 'a@x.test')];

        $plan = $this->planner()->plan($users, null, []);
        $this->assertSame(['g1' => 'conflict'], self::types($plan));
        $links = self::linksAfter($plan, []);
        $this->assertSame([], $this->planner()->plan($users, null, $links)->actions);

        // The local account was deleted, so the directory object gets its address.
        $this->local = [];
        $this->assertSame(['g1' => 'create'], self::types($this->planner()->plan($users, null, $links)));
    }

    public function testAChangedAddressRenamesTheAccountUnlessTheNewAddressIsTaken(): void
    {
        $links = self::linksAfter($this->planner()->plan([self::user('g1', 'a@x.test')], null, []), []);
        $this->local = ['a@x.test'];

        $plan = $this->planner()->plan([self::user('g1', 'new@x.test')], null, $links);
        $this->assertSame(['g1' => 'rename'], self::types($plan));
        $this->assertSame('a@x.test', $plan->actions[0]->link->address);

        $this->local = ['a@x.test', 'new@x.test'];
        $blocked = $this->planner()->plan([self::user('g1', 'new@x.test')], null, $links);
        $this->assertSame(['g1' => 'conflict'], self::types($blocked));
        // The account keeps its old address and stays owned.
        $after = self::linksAfter($blocked, $links);
        $this->assertSame('a@x.test', $after['g1']->address);
        $this->assertSame(ReplicatedAccount::STATE_ACTIVE, $after['g1']->state);
        $this->assertSame([], $this->planner()->plan([self::user('g1', 'new@x.test')], null, $after)->actions);
    }

    public function testARemovedObjectIsDisabledOnceAndEnabledWhenItReturns(): void
    {
        $users = [self::user('g1', 'a@x.test'), self::user('g2', 'b@x.test')];
        $links = self::linksAfter($this->planner()->plan($users, null, []), []);

        $plan = $this->planner()->plan([$users[0]], null, $links);
        $this->assertSame(['g2' => 'disable'], self::types($plan));
        $links = self::linksAfter($plan, $links);
        $this->assertSame([], $this->planner()->plan([$users[0]], null, $links)->actions);

        $this->assertSame(['g2' => 'enable'], self::types($this->planner()->plan($users, null, $links)));
    }

    public function testAnObjectThatLosesItsAddressDisablesItsAccount(): void
    {
        $links = self::linksAfter($this->planner()->plan([self::user('g1', 'a@x.test')], null, []), []);
        $moved = new SourceAccount('g1', ReplicatedAccount::KIND_USER, 'CN=g1', 'a@other.test', SourceAccount::SKIP_OTHER_DOMAIN);

        $plan = $this->planner()->plan([$moved], null, $links);
        $this->assertSame(['g1' => 'disable'], self::types($plan));
        $this->assertSame([], $this->planner()->plan([$moved], null, self::linksAfter($plan, $links))->actions);
    }

    public function testSkipsAreLoggedOnceAndForgottenWhenTheObjectIsGone(): void
    {
        $computer = new SourceAccount('g9', ReplicatedAccount::KIND_USER, 'CN=PC', '', SourceAccount::SKIP_NO_ADDRESS);
        $plan = $this->planner()->plan([$computer], null, []);
        $this->assertSame(['g9' => 'skip'], self::types($plan));
        $links = self::linksAfter($plan, []);

        $this->assertSame([], $this->planner()->plan([$computer], null, $links)->actions);
        $this->assertSame(
            ['g1' => 'create', 'g9' => 'forget'],
            self::types($this->planner()->plan([self::user('g1', 'a@x.test')], null, $links)),
        );
    }

    public function testTheSecondObjectWithAnAddressIsSkipped(): void
    {
        $plan = $this->planner()->plan([self::user('g1', 'a@x.test')], [self::group('g2', 'a@x.test', [])], []);

        $this->assertSame(['g1' => 'create', 'g2' => 'skip'], self::types($plan));
        $this->assertSame(SourceAccount::SKIP_DUPLICATE_ADDRESS, $plan->actions[1]->detail);
    }

    public function testGroupMembersResolveToReplicatedAddresses(): void
    {
        $users = [self::user('u1', 'a@x.test'), self::user('u2', 'b@x.test')];
        $group = self::group('g1', 'team@x.test', ['cn=u2,dc=x', 'CN=u1,DC=x', 'CN=outside,DC=y', 'CN=g1,DC=x']);

        $plan = $this->planner()->plan($users, [$group], []);
        $action = array_values(array_filter($plan->actions, fn($a) => $a->guid === 'g1'))[0];

        $this->assertSame(ReplicationAction::CREATE, $action->type);
        $this->assertSame(['a@x.test', 'b@x.test'], $action->members);
        $this->assertSame('unresolved_members:1', $action->detail);

        $links = self::linksAfter($plan, []);
        $changed = self::group('g1', 'team@x.test', ['CN=u1,DC=x']);
        $this->assertSame(['g1' => 'update'], self::types($this->planner()->plan($users, [$changed], $links)));
    }

    public function testGroupLinksStayWhenGroupsAreNotReplicated(): void
    {
        $users = [self::user('u1', 'a@x.test')];
        $links = self::linksAfter($this->planner()->plan($users, [self::group('g1', 'team@x.test', [])], []), []);

        $this->assertSame([], $this->planner()->plan($users, null, $links)->actions);
    }

    public function testAnEmptyDirectoryDisablesNothing(): void
    {
        $links = self::linksAfter($this->planner()->plan([self::user('g1', 'a@x.test')], null, []), []);

        $plan = $this->planner()->plan([], null, $links);
        $this->assertSame([], $plan->actions);
        $this->assertSame(1, $plan->heldBack);
    }

    public function testRemovingMostAccountsIsHeldBackUnlessAllowed(): void
    {
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = self::user("g{$i}", "u{$i}@x.test");
        }
        $links = self::linksAfter($this->planner()->plan($users, null, []), []);

        // Five of ten is half, not more than half.
        $this->assertCount(5, $this->planner()->plan(array_slice($users, 0, 5), null, $links)->actions);

        $held = $this->planner()->plan(array_slice($users, 0, 4), null, $links);
        $this->assertSame([], $held->actions);
        $this->assertSame(6, $held->heldBack);

        $this->assertCount(6, $this->planner(allowMassDisable: true)->plan(array_slice($users, 0, 4), null, $links)->actions);
    }
}
