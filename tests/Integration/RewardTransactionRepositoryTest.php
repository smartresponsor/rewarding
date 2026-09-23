<?php

declare(strict_types=1);

namespace App\Rewarding\Tests\Integration;

use App\Rewarding\Entity\RewardTransactionEntity;
use App\Rewarding\Enum\RewardTransactionType;
use App\Rewarding\Exception\RewardConcurrencyException;
use App\Rewarding\Exception\RewardIdempotencyConflictException;
use App\Rewarding\Repository\RewardTransactionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;

final class RewardTransactionRepositoryTest extends TestCase
{
    private RewardTransactionRepository $repository;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE reward_transaction (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                account_id VARCHAR(64) NOT NULL,
                ledger_version INTEGER NOT NULL,
                type VARCHAR(32) NOT NULL,
                points INTEGER NOT NULL,
                idempotency_key VARCHAR(191) NOT NULL,
                occurred_at TEXT NOT NULL,
                reference VARCHAR(191) DEFAULT NULL,
                reverses_transaction_id VARCHAR(64) DEFAULT NULL
            )'
        );
        $connection->executeStatement('CREATE UNIQUE INDEX reward_transaction_account_version_uidx ON reward_transaction (account_id, ledger_version)');
        $connection->executeStatement('CREATE UNIQUE INDEX reward_transaction_idempotency_uidx ON reward_transaction (account_id, idempotency_key)');
        $connection->executeStatement('CREATE UNIQUE INDEX reward_transaction_reversal_uidx ON reward_transaction (reverses_transaction_id)');

        $this->repository = new RewardTransactionRepository($connection);
    }

    public function testAppendPersistsAndReadsLedgerInVersionOrder(): void
    {
        $first = $this->transaction('tx-1', 1, 'earn-1', 100);
        $second = $this->transaction('tx-2', 2, 'earn-2', 25);

        self::assertSame($first, $this->repository->append($first, 0));
        self::assertSame($second, $this->repository->append($second, 1));

        $ledger = $this->repository->findForAccount('account-1');
        self::assertCount(2, $ledger);
        self::assertSame(['tx-1', 'tx-2'], array_map(static fn (RewardTransactionEntity $item): string => $item->id, $ledger));
        self::assertSame(2, $ledger[1]->ledgerVersion);
        self::assertSame('tx-2', $this->repository->findById('tx-2')?->id);
        self::assertNull($this->repository->findById('missing'));
    }

    public function testIdempotentReplayReturnsExistingEntry(): void
    {
        $first = $this->transaction('tx-1', 1, 'same-key', 100);
        $this->repository->append($first, 0);

        $replay = $this->transaction('tx-ignored', 2, 'same-key', 100);

        self::assertSame('tx-1', $this->repository->append($replay, 1)->id);
        self::assertCount(1, $this->repository->findForAccount('account-1'));
    }

    public function testIdempotencyConflictIsRejected(): void
    {
        $this->repository->append($this->transaction('tx-1', 1, 'same-key', 100), 0);

        $this->expectException(RewardIdempotencyConflictException::class);
        $this->repository->append($this->transaction('tx-2', 2, 'same-key', 101), 1);
    }

    public function testStaleExpectedVersionIsRejected(): void
    {
        $this->repository->append($this->transaction('tx-1', 1, 'earn-1', 100), 0);

        $this->expectException(RewardConcurrencyException::class);
        $this->repository->append($this->transaction('tx-2', 1, 'earn-2', 25), 0);
    }

    public function testHydrationPreservesNullableAndReversalFields(): void
    {
        $first = new RewardTransactionEntity(
            'tx-1',
            'account-1',
            1,
            RewardTransactionType::Earn,
            100,
            'earn-1',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );
        $this->repository->append($first, 0);

        $second = new RewardTransactionEntity(
            'tx-2',
            'account-1',
            2,
            RewardTransactionType::Reverse,
            -100,
            'reverse-1',
            new \DateTimeImmutable('2026-09-22T13:00:00+00:00'),
            'order-1',
            'tx-1',
        );
        $this->repository->append($second, 1);

        $firstHydrated = $this->repository->findById('tx-1');
        $secondHydrated = $this->repository->findById('tx-2');

        self::assertNotNull($secondHydrated);
        self::assertNull($firstHydrated?->reference);
        self::assertNull($firstHydrated?->reversesTransactionId);
        self::assertSame('order-1', $secondHydrated->reference);
        self::assertSame('tx-1', $secondHydrated->reversesTransactionId);
        self::assertSame('tx-2', $this->repository->findReversalOf('tx-1')?->id);
        self::assertNull($this->repository->findReversalOf('missing'));
    }

    public function testDatabaseUniquenessRejectsSecondReversalOfSameSource(): void
    {
        $this->repository->append($this->transaction('tx-1', 1, 'earn-1', 100), 0);
        $firstReversal = new RewardTransactionEntity(
            'tx-2',
            'account-1',
            2,
            RewardTransactionType::Reverse,
            -100,
            'reverse-1',
            new \DateTimeImmutable('2026-09-22T13:00:00+00:00'),
            'order-1',
            'tx-1',
        );
        $this->repository->append($firstReversal, 1);

        $duplicateReversal = new RewardTransactionEntity(
            'tx-3',
            'account-1',
            3,
            RewardTransactionType::Reverse,
            -100,
            'reverse-2',
            new \DateTimeImmutable('2026-09-22T14:00:00+00:00'),
            'order-1',
            'tx-1',
        );

        $this->expectException(RewardConcurrencyException::class);
        $this->repository->append($duplicateReversal, 2);
    }

    public function testPrimaryKeyCollisionIsReportedAsConcurrencyConflict(): void
    {
        $this->repository->append($this->transaction('tx-shared', 1, 'account-1-key', 100), 0);

        $collision = new RewardTransactionEntity(
            'tx-shared',
            'account-2',
            1,
            RewardTransactionType::Earn,
            25,
            'account-2-key',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
        );

        $this->expectException(RewardConcurrencyException::class);
        $this->repository->append($collision, 0);
    }

    public function testFindByIdempotencyKeyReturnsPersistedEntryAndMissingValue(): void
    {
        $this->repository->append($this->transaction('tx-1', 1, 'earn-1', 100), 0);

        self::assertSame('tx-1', $this->repository->findByIdempotencyKey('account-1', 'earn-1')?->id);
        self::assertNull($this->repository->findByIdempotencyKey('account-1', 'missing'));
    }

    public function testUniqueRaceRecoversExistingIdempotentEntry(): void
    {
        $connection = $this->createStub(Connection::class);
        $driverException = new class('unique violation') extends \RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '23505';
            }
        };

        $connection
            ->method('transactional')
            ->willThrowException(new UniqueConstraintViolationException($driverException, null));
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 'tx-existing',
                'account_id' => 'account-1',
                'ledger_version' => 1,
                'type' => 'earn',
                'points' => 100,
                'idempotency_key' => 'earn-1',
                'occurred_at' => '2026-09-22T12:00:00+00:00',
                'reference' => 'order-1',
                'reverses_transaction_id' => null,
            ]);

        $repository = new RewardTransactionRepository($connection);
        $candidate = new RewardTransactionEntity(
            'tx-racing',
            'account-1',
            1,
            RewardTransactionType::Earn,
            100,
            'earn-1',
            new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            'order-1',
        );

        self::assertSame('tx-existing', $repository->append($candidate, 0)->id);
    }

    public function testCandidateVersionMustFollowExpectedVersion(): void
    {
        $this->expectException(RewardConcurrencyException::class);
        $this->repository->append($this->transaction('tx-1', 2, 'earn-1', 100), 0);
    }

    private function transaction(string $id, int $version, string $idempotencyKey, int $points): RewardTransactionEntity
    {
        return new RewardTransactionEntity(
            id: $id,
            accountId: 'account-1',
            ledgerVersion: $version,
            type: RewardTransactionType::Earn,
            points: $points,
            idempotencyKey: $idempotencyKey,
            occurredAt: new \DateTimeImmutable('2026-09-22T12:00:00+00:00'),
            reference: 'order-1',
        );
    }
}
