<?php

declare(strict_types=1);

namespace App\Rewarding\Service;

use App\Rewarding\Entity\RewardTransactionEntity;
use App\Rewarding\Enum\RewardTransactionType;
use App\Rewarding\Exception\RewardAlreadyReversedException;
use App\Rewarding\Exception\RewardIdempotencyConflictException;
use App\Rewarding\Exception\RewardInsufficientBalanceException;
use App\Rewarding\RepositoryInterface\RewardTransactionRepositoryInterface;

/** Executes replay-safe non-monetary points mutations against an append-only ledger. */
final readonly class RewardLedgerService
{
    public function __construct(private RewardTransactionRepositoryInterface $transactions)
    {
    }

    /** Derives the current spendable points balance from immutable ledger entries. */
    public function balance(string $accountId): int
    {
        return array_sum(array_map(
            static fn (RewardTransactionEntity $transaction): int => $transaction->points,
            $this->transactions->findForAccount($accountId),
        ));
    }

    /** Appends an idempotent positive earn entry at the caller-observed ledger version. */
    public function earn(string $transactionId, string $accountId, int $points, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion, ?string $reference = null): RewardTransactionEntity
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Earn points must be positive.');
        }

        return $this->appendIdempotently(new RewardTransactionEntity($transactionId, $accountId, $expectedVersion + 1, RewardTransactionType::Earn, $points, $idempotencyKey, $occurredAt, $reference), $expectedVersion);
    }

    /** Appends an idempotent redemption debit when sufficient points remain available. */
    public function redeem(string $transactionId, string $accountId, int $points, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion, ?string $reference = null): RewardTransactionEntity
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Redeem points must be positive.');
        }

        return $this->debit(RewardTransactionType::Redeem, $transactionId, $accountId, $points, $idempotencyKey, $occurredAt, $expectedVersion, $reference);
    }

    /** Appends an explicit replay-safe expiry debit without deleting historical credits. */
    public function expire(string $transactionId, string $accountId, int $points, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion, ?string $reference = null): RewardTransactionEntity
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Expired points must be positive.');
        }

        return $this->debit(RewardTransactionType::Expire, $transactionId, $accountId, $points, $idempotencyKey, $occurredAt, $expectedVersion, $reference);
    }

    /** Appends a signed manual adjustment while preserving the non-negative balance invariant. */
    public function adjust(string $transactionId, string $accountId, int $points, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion, ?string $reference = null): RewardTransactionEntity
    {
        if (0 === $points) {
            throw new \InvalidArgumentException('Adjustment points must not be zero.');
        }
        if ($points < 0 && $this->balance($accountId) < abs($points)) {
            throw new RewardInsufficientBalanceException('Reward adjustment exceeds the available points balance.');
        }

        return $this->appendIdempotently(new RewardTransactionEntity($transactionId, $accountId, $expectedVersion + 1, RewardTransactionType::Adjust, $points, $idempotencyKey, $occurredAt, $reference), $expectedVersion);
    }

    /** Appends the exact opposite of a prior ledger entry and links the reversal to its source. */
    public function reverse(string $transactionId, string $originalTransactionId, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion): RewardTransactionEntity
    {
        $original = $this->transactions->findById($originalTransactionId);
        if (null === $original) {
            throw new \InvalidArgumentException('Reward transaction to reverse was not found.');
        }

        $candidate = new RewardTransactionEntity($transactionId, $original->accountId, $expectedVersion + 1, RewardTransactionType::Reverse, -$original->points, $idempotencyKey, $occurredAt, $original->reference, $original->id);
        $existing = $this->transactions->findByIdempotencyKey($original->accountId, $idempotencyKey);
        if (null !== $existing) {
            if ($existing->fingerprint() !== $candidate->fingerprint()) {
                throw new RewardIdempotencyConflictException('Reward idempotency key was reused with different mutation data.');
            }

            return $existing;
        }
        if (null !== $this->transactions->findReversalOf($original->id)) {
            throw new RewardAlreadyReversedException('Reward transaction has already been reversed.');
        }
        if ($original->points > 0 && $this->balance($original->accountId) < $original->points) {
            throw new RewardInsufficientBalanceException('Reward reversal would make the points balance negative.');
        }

        return $this->transactions->append($candidate, $expectedVersion);
    }

    private function debit(RewardTransactionType $type, string $transactionId, string $accountId, int $points, string $idempotencyKey, \DateTimeImmutable $occurredAt, int $expectedVersion, ?string $reference): RewardTransactionEntity
    {
        if ($this->balance($accountId) < $points) {
            throw new RewardInsufficientBalanceException('Reward debit exceeds the available points balance.');
        }

        return $this->appendIdempotently(new RewardTransactionEntity($transactionId, $accountId, $expectedVersion + 1, $type, -$points, $idempotencyKey, $occurredAt, $reference), $expectedVersion);
    }

    private function appendIdempotently(RewardTransactionEntity $candidate, int $expectedVersion): RewardTransactionEntity
    {
        $existing = $this->transactions->findByIdempotencyKey($candidate->accountId, $candidate->idempotencyKey);
        if (null !== $existing) {
            if ($existing->fingerprint() !== $candidate->fingerprint()) {
                throw new RewardIdempotencyConflictException('Reward idempotency key was reused with different mutation data.');
            }

            return $existing;
        }

        return $this->transactions->append($candidate, $expectedVersion);
    }
}
