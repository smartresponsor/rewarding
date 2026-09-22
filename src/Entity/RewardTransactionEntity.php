<?php

declare(strict_types=1);

namespace App\Rewarding\Entity;

use App\Rewarding\Enum\RewardTransactionType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Immutable points-ledger entry. Positive points credit an account; negative points debit it. */
#[ORM\Entity]
#[ORM\Table(
    name: 'reward_transaction',
    indexes: [
        new ORM\Index(name: 'reward_transaction_account_occurred_idx', columns: ['account_id', 'occurred_at']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'reward_transaction_account_version_uidx', columns: ['account_id', 'ledger_version']),
        new ORM\UniqueConstraint(name: 'reward_transaction_idempotency_uidx', columns: ['account_id', 'idempotency_key']),
    ],
)]
final readonly class RewardTransactionEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 64)]
        public string $id,
        #[ORM\Column(name: 'account_id', type: Types::STRING, length: 64)]
        public string $accountId,
        #[ORM\Column(name: 'ledger_version', type: Types::INTEGER)]
        public int $ledgerVersion,
        #[ORM\Column(type: Types::STRING, length: 32, enumType: RewardTransactionType::class)]
        public RewardTransactionType $type,
        #[ORM\Column(type: Types::INTEGER)]
        public int $points,
        #[ORM\Column(name: 'idempotency_key', type: Types::STRING, length: 191)]
        public string $idempotencyKey,
        #[ORM\Column(name: 'occurred_at', type: Types::DATETIMETZ_IMMUTABLE)]
        public \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: Types::STRING, length: 191, nullable: true)]
        public ?string $reference = null,
        #[ORM\Column(name: 'reverses_transaction_id', type: Types::STRING, length: 64, nullable: true)]
        public ?string $reversesTransactionId = null,
    ) {
        if ('' === trim($id) || '' === trim($accountId) || '' === trim($idempotencyKey)) {
            throw new \InvalidArgumentException('Reward transaction identity fields must not be empty.');
        }

        if ($ledgerVersion <= 0) {
            throw new \InvalidArgumentException('Reward ledger version must be positive.');
        }

        if (0 === $points) {
            throw new \InvalidArgumentException('Reward transaction points must not be zero.');
        }
    }

    /** Returns a deterministic mutation fingerprint used to validate idempotent replay. */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->accountId,
            $this->type->value,
            (string) $this->points,
            $this->reference ?? '',
            $this->reversesTransactionId ?? '',
        ]));
    }
}
