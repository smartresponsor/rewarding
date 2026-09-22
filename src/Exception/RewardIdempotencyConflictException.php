<?php

declare(strict_types=1);

namespace App\Rewarding\Exception;

/** Signals reuse of an idempotency key for a materially different reward mutation. */
final class RewardIdempotencyConflictException extends \RuntimeException
{
}
