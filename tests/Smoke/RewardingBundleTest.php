<?php

declare(strict_types=1);

namespace App\Rewarding\Tests\Smoke;

use App\Rewarding\RewardingBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Verifies the reusable Rewarding bundle surface without introducing product behavior.
 */
final class RewardingBundleTest extends TestCase
{
    /**
     * Confirms the Symfony bundle contract required for host composition.
     */
    public function testBundleSurface(): void
    {
        self::assertInstanceOf(Bundle::class, new RewardingBundle());
    }
}
