<?php

declare(strict_types=1);

namespace App\Rewarding\RepositoryInterface;

use App\Rewarding\Entity\RewardTransactionEntity;

/** Persistence boundary for the immutable reward transaction ledger. */
interface RewardTransactionRepositoryInterface
{
    /** @return list<RewardTransactionEntity> */
    public function findForAccount(string $accountId): array;

    /** Finds one immutable ledger entry by its transaction identity. */
    public function findById(string $transactionId): ?RewardTransactionEntity;

    /** Finds the previously accepted mutation for an account-scoped idempotency key. */
    public function findByIdempotencyKey(string $accountId, string $idempotencyKey): ?RewardTransactionEntity;

    /** Finds the immutable reversal already linked to one source transaction. */
    public function findReversalOf(string $transactionId): ?RewardTransactionEntity;

    /** Appends one immutable entry only when the account ledger still has the expected version. */
    public function append(RewardTransactionEntity $transaction, int $expectedVersion): RewardTransactionEntity;
}
