<?php

declare(strict_types=1);

namespace App\Rewarding\Entity\Reward;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Canonical Rewarding persistence identity and relationship-composition anchor. */
#[ORM\Entity]
#[ORM\Table(name: 'reward')]
final class RewardEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    public string $id;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;
}
