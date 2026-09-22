<?php

declare(strict_types=1);

namespace App\Rewarding\Exception;

/** Signals that optimistic ledger version comparison rejected a concurrent mutation. */
final class RewardConcurrencyException extends \RuntimeException
{
}
