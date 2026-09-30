<?php

declare(strict_types=1);

namespace App\Rewarding\Tests\Unit;

use App\Rewarding\Entity\Reward\RewardEntity;
use App\Rewarding\Entity\RewardAccountEntity;
use App\Rewarding\Entity\RewardTransactionEntity;
use App\Rewarding\Enum\RewardTransactionType;
use PHPUnit\Framework\TestCase;

final class RewardEntityTest extends TestCase
{
    public function testRootRewardEntityExposesCanonicalIdentity(): void
    {
        $createdAt = new \DateTimeImmutable('2026-09-30T00:00:00+00:00');
        $reward = new RewardEntity();
        $reward->id = 'reward-1';
        $reward->createdAt = $createdAt;

        self::assertSame('reward-1', $reward->id);
        self::assertSame($createdAt, $reward->createdAt);
    }

    public function testAccountAndTransactionExposeStableIdentityAndFingerprint(): void
    {
        $at = new \DateTimeImmutable('2026-09-22T12:00:00+00:00');
        $account = new RewardAccountEntity('account-1', 'member-1', $at);
        $transaction = new RewardTransactionEntity(
            id: 'tx-1',
            accountId: $account->id,
            ledgerVersion: 1,
            type: RewardTransactionType::Earn,
            points: 100,
            idempotencyKey: 'earn-1',
            occurredAt: $at,
            reference: 'order-1',
            reversesTransactionId: 'tx-0',
        );

        self::assertSame('member-1', $account->memberReference);
        self::assertSame(
            hash('sha256', 'account-1|earn|100|order-1|tx-0'),
            $transaction->fingerprint(),
        );
    }

    public function testFingerprintIsStableWithNullableReferences(): void
    {
        $transaction = new RewardTransactionEntity(
            'tx-1',
            'account-1',
            1,
            RewardTransactionType::Earn,
            1,
            'earn-1',
            new \DateTimeImmutable(),
        );

        self::assertSame(hash('sha256', 'account-1|earn|1||'), $transaction->fingerprint());
    }

    public function testAccountRejectsEmptyIdentityFields(): void
    {
        foreach ([
            ['', 'member-1'],
            ['account-1', ''],
        ] as [$id, $memberReference]) {
            try {
                new RewardAccountEntity($id, $memberReference, new \DateTimeImmutable());
                self::fail('Expected invalid reward account identity.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testTransactionRejectsInvalidIdentityVersionAndPoints(): void
    {
        foreach ([
            ['', 'account-1', 1, 'idem-1', 1],
            ['tx-1', '', 1, 'idem-1', 1],
            ['tx-1', 'account-1', 1, '', 1],
            ['tx-1', 'account-1', 0, 'idem-1', 1],
            ['tx-1', 'account-1', 1, 'idem-1', 0],
        ] as [$id, $accountId, $version, $idempotencyKey, $points]) {
            try {
                new RewardTransactionEntity(
                    $id,
                    $accountId,
                    $version,
                    RewardTransactionType::Earn,
                    $points,
                    $idempotencyKey,
                    new \DateTimeImmutable(),
                );
                self::fail('Expected invalid reward transaction invariant.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
