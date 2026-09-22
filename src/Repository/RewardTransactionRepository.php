<?php

declare(strict_types=1);

namespace App\Rewarding\Repository;

use App\Rewarding\Entity\RewardTransactionEntity;
use App\Rewarding\Enum\RewardTransactionType;
use App\Rewarding\Exception\RewardConcurrencyException;
use App\Rewarding\Exception\RewardIdempotencyConflictException;
use App\Rewarding\RepositoryInterface\RewardTransactionRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;

/** Durable append-only repository for the non-monetary reward ledger. */
final readonly class RewardTransactionRepository implements RewardTransactionRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findForAccount(string $accountId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, account_id, ledger_version, type, points, idempotency_key, occurred_at, reference, reverses_transaction_id
             FROM reward_transaction
             WHERE account_id = :account_id
             ORDER BY ledger_version ASC',
            ['account_id' => $accountId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function findById(string $transactionId): ?RewardTransactionEntity
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, account_id, ledger_version, type, points, idempotency_key, occurred_at, reference, reverses_transaction_id
             FROM reward_transaction
             WHERE id = :id',
            ['id' => $transactionId],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findByIdempotencyKey(string $accountId, string $idempotencyKey): ?RewardTransactionEntity
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, account_id, ledger_version, type, points, idempotency_key, occurred_at, reference, reverses_transaction_id
             FROM reward_transaction
             WHERE account_id = :account_id AND idempotency_key = :idempotency_key',
            ['account_id' => $accountId, 'idempotency_key' => $idempotencyKey],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function append(RewardTransactionEntity $transaction, int $expectedVersion): RewardTransactionEntity
    {
        if ($transaction->ledgerVersion !== $expectedVersion + 1) {
            throw new RewardConcurrencyException('Reward transaction ledger version does not match the expected append version.');
        }

        try {
            return $this->connection->transactional(function (Connection $connection) use ($transaction, $expectedVersion): RewardTransactionEntity {
                $existing = $this->findByIdempotencyKey($transaction->accountId, $transaction->idempotencyKey);
                if (null !== $existing) {
                    return $this->resolveIdempotentReplay($existing, $transaction);
                }

                $currentVersion = (int) $connection->fetchOne(
                    'SELECT COALESCE(MAX(ledger_version), 0) FROM reward_transaction WHERE account_id = :account_id',
                    ['account_id' => $transaction->accountId],
                );
                if ($currentVersion !== $expectedVersion) {
                    throw new RewardConcurrencyException('Reward ledger version changed before append.');
                }

                $connection->insert('reward_transaction', [
                    'id' => $transaction->id,
                    'account_id' => $transaction->accountId,
                    'ledger_version' => $transaction->ledgerVersion,
                    'type' => $transaction->type->value,
                    'points' => $transaction->points,
                    'idempotency_key' => $transaction->idempotencyKey,
                    'occurred_at' => $transaction->occurredAt,
                    'reference' => $transaction->reference,
                    'reverses_transaction_id' => $transaction->reversesTransactionId,
                ], [
                    'ledger_version' => Types::INTEGER,
                    'points' => Types::INTEGER,
                    'occurred_at' => Types::DATETIMETZ_IMMUTABLE,
                ]);

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByIdempotencyKey($transaction->accountId, $transaction->idempotencyKey);
            if (null !== $existing) {
                return $this->resolveIdempotentReplay($existing, $transaction);
            }

            throw new RewardConcurrencyException('Reward ledger append lost a concurrent version race.', previous: $exception);
        }
    }

    private function resolveIdempotentReplay(RewardTransactionEntity $existing, RewardTransactionEntity $candidate): RewardTransactionEntity
    {
        if ($existing->fingerprint() !== $candidate->fingerprint()) {
            throw new RewardIdempotencyConflictException('Reward idempotency key was reused with different mutation data.');
        }

        return $existing;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RewardTransactionEntity
    {
        return new RewardTransactionEntity(
            id: (string) $row['id'],
            accountId: (string) $row['account_id'],
            ledgerVersion: (int) $row['ledger_version'],
            type: RewardTransactionType::from((string) $row['type']),
            points: (int) $row['points'],
            idempotencyKey: (string) $row['idempotency_key'],
            occurredAt: new \DateTimeImmutable((string) $row['occurred_at']),
            reference: null === $row['reference'] ? null : (string) $row['reference'],
            reversesTransactionId: null === $row['reverses_transaction_id'] ? null : (string) $row['reverses_transaction_id'],
        );
    }
}
