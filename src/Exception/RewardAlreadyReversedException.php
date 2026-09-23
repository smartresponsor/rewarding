<?php

declare(strict_types=1);

namespace App\Rewarding\Exception;

/** Raised when a second reversal is attempted for the same immutable ledger entry. */
final class RewardAlreadyReversedException extends \RuntimeException
{
}
