<?php

declare(strict_types=1);

namespace App\Rewarding\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Represents a non-monetary loyalty account owned by an external member reference. */
#[ORM\Entity]
#[ORM\Table(name: 'reward_account')]
final readonly class RewardAccountEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 64)]
        public string $id,
        #[ORM\Column(name: 'member_reference', type: Types::STRING, length: 191, unique: true)]
        public string $memberReference,
        #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
        public \DateTimeImmutable $createdAt,
    ) {
        if ('' === trim($id)) {
            throw new \InvalidArgumentException('Reward account id must not be empty.');
        }

        if ('' === trim($memberReference)) {
            throw new \InvalidArgumentException('Reward member reference must not be empty.');
        }
    }
}
