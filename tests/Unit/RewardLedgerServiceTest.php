<?php

declare(strict_types=1);

namespace App\Rewarding\Tests\Unit;

use App\Rewarding\Entity\RewardTransactionEntity;
use App\Rewarding\Exception\RewardConcurrencyException;
use App\Rewarding\Exception\RewardIdempotencyConflictException;
use App\Rewarding\Exception\RewardInsufficientBalanceException;
use App\Rewarding\RepositoryInterface\RewardTransactionRepositoryInterface;
use App\Rewarding\Service\RewardLedgerService;
use PHPUnit\Framework\TestCase;

final class RewardLedgerServiceTest extends TestCase
{
    public function testEarnRedeemAndReplayProduceAuditableBalance(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $earned = $service->earn('tx-1', 'account-1', 100, 'earn-order-1', $at, 0, 'order-1');
        $replayed = $service->earn('tx-ignored', 'account-1', 100, 'earn-order-1', $at, 1, 'order-1');
        $service->redeem('tx-2', 'account-1', 40, 'redeem-order-2', $at, 1, 'order-2');

        self::assertSame($earned, $replayed);
        self::assertSame(60, $service->balance('account-1'));
        self::assertCount(2, $repository->findForAccount('account-1'));
    }

    public function testIdempotencyKeyCannotBeReusedForDifferentMutation(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $service->earn('tx-1', 'account-1', 100, 'same-key', $at, 0);
        $this->expectException(RewardIdempotencyConflictException::class);
        $service->earn('tx-2', 'account-1', 101, 'same-key', $at, 1);
    }

    public function testInsufficientBalanceRejectsRedemptionWithoutLedgerMutation(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        try {
            $service->redeem('tx-1', 'account-1', 1, 'redeem-1', new \DateTimeImmutable(), 0);
            self::fail('Expected insufficient balance exception.');
        } catch (RewardInsufficientBalanceException) {
            self::assertSame([], $repository->findForAccount('account-1'));
        }
    }

    public function testStaleVersionRejectsConcurrentRedemption(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $service->earn('tx-1', 'account-1', 100, 'earn-1', $at, 0);
        $service->redeem('tx-2', 'account-1', 60, 'redeem-1', $at, 1);
        $this->expectException(RewardConcurrencyException::class);
        $service->redeem('tx-3', 'account-1', 30, 'redeem-2', $at, 1);
    }

    public function testExpireAndAdjustPreserveNonNegativeLedgerSemantics(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');

        $service->earn('tx-1', 'account-1', 100, 'earn-1', $at, 0);
        $expired = $service->expire('tx-2', 'account-1', 20, 'expire-1', $at, 1, 'expiry-cycle-1');
        $creditAdjustment = $service->adjust('tx-3', 'account-1', 10, 'adjust-1', $at, 2, 'manual-credit');
        $debitAdjustment = $service->adjust('tx-4', 'account-1', -15, 'adjust-2', $at, 3, 'manual-debit');

        self::assertSame(-20, $expired->points);
        self::assertSame(10, $creditAdjustment->points);
        self::assertSame(-15, $debitAdjustment->points);
        self::assertSame(75, $service->balance('account-1'));
    }

    public function testInvalidMutationAmountsAreRejected(): void
    {
        $service = new RewardLedgerService(new RewardInMemoryTransactionRepository());
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');

        foreach ([
            static fn () => $service->earn('tx-1', 'account-1', 0, 'earn-1', $at, 0),
            static fn () => $service->redeem('tx-2', 'account-1', 0, 'redeem-1', $at, 0),
            static fn () => $service->expire('tx-3', 'account-1', 0, 'expire-1', $at, 0),
            static fn () => $service->adjust('tx-4', 'account-1', 0, 'adjust-1', $at, 0),
        ] as $mutation) {
            try {
                $mutation();
                self::fail('Expected invalid reward mutation amount.');
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testNegativeAdjustmentCannotExceedAvailableBalance(): void
    {
        $service = new RewardLedgerService(new RewardInMemoryTransactionRepository());
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $service->earn('tx-1', 'account-1', 10, 'earn-1', $at, 0);

        $this->expectException(RewardInsufficientBalanceException::class);
        $service->adjust('tx-2', 'account-1', -11, 'adjust-1', $at, 1);
    }

    public function testReverseRequiresExistingTransaction(): void
    {
        $service = new RewardLedgerService(new RewardInMemoryTransactionRepository());

        $this->expectException(\InvalidArgumentException::class);
        $service->reverse('tx-1', 'missing', 'reverse-1', new \DateTimeImmutable(), 0);
    }

    public function testReverseCreditRequiresAvailablePoints(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $service->earn('tx-1', 'account-1', 100, 'earn-1', $at, 0);
        $service->redeem('tx-2', 'account-1', 80, 'redeem-1', $at, 1);

        $this->expectException(RewardInsufficientBalanceException::class);
        $service->reverse('tx-3', 'tx-1', 'reverse-1', $at, 2);
    }

    public function testReverseIsAppendOnlyAndHistoricallyExplainable(): void
    {
        $repository = new RewardInMemoryTransactionRepository();
        $service = new RewardLedgerService($repository);
        $at = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');
        $service->earn('tx-1', 'account-1', 80, 'earn-1', $at, 0, 'order-1');
        $reversal = $service->reverse('tx-2', 'tx-1', 'reverse-1', $at, 1);

        self::assertSame(-80, $reversal->points);
        self::assertSame('tx-1', $reversal->reversesTransactionId);
        self::assertSame(0, $service->balance('account-1'));
    }
}

final class RewardInMemoryTransactionRepository implements RewardTransactionRepositoryInterface
{
    /** @var list<RewardTransactionEntity> */
    private array $transactions = [];

    public function findForAccount(string $accountId): array
    {
        return array_values(array_filter($this->transactions, static fn (RewardTransactionEntity $transaction): bool => $transaction->accountId === $accountId));
    }

    public function findById(string $transactionId): ?RewardTransactionEntity
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->id === $transactionId) {
                return $transaction;
            }
        }

        return null;
    }

    public function findByIdempotencyKey(string $accountId, string $idempotencyKey): ?RewardTransactionEntity
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->accountId === $accountId && $transaction->idempotencyKey === $idempotencyKey) {
                return $transaction;
            }
        }

        return null;
    }

    public function append(RewardTransactionEntity $transaction, int $expectedVersion): RewardTransactionEntity
    {
        if (count($this->findForAccount($transaction->accountId)) !== $expectedVersion) {
            throw new RewardConcurrencyException('Reward ledger version changed before append.');
        }

        $this->transactions[] = $transaction;

        return $transaction;
    }
}
