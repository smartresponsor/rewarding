<?php

declare(strict_types=1);

namespace App\Rewarding\Exception;

/** Signals that a points debit cannot be accepted without making the ledger balance negative. */
final class RewardInsufficientBalanceException extends \RuntimeException
{
}
