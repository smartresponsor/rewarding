<?php

declare(strict_types=1);

namespace App\Rewarding\Enum;

/** Identifies the business reason for an immutable points-ledger entry. */
enum RewardTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjust = 'adjust';
    case Reverse = 'reverse';
}
