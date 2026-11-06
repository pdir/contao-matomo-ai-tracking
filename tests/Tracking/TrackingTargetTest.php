<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\Tracking;

use Pdir\MatomoAiTrackingBundle\Tracking\TrackingTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrackingTargetTest extends TestCase
{
    public function testCreatesTarget(): void
    {
        $target = TrackingTarget::create('4', ' https://stats.maklermodul.de/ ');

        $this->assertSame(4, $target?->siteId);
        $this->assertSame('https://stats.maklermodul.de/matomo.php', $target?->getEndpoint());
    }

    #[DataProvider('invalidProvider')]
    public function testRejectsInvalidInput(int|string|null $siteId, string|null $url): void
    {
        $this->assertNull(TrackingTarget::create($siteId, $url));
    }

    public static function invalidProvider(): iterable
    {
        yield 'keine Site-ID' => [null, 'https://matomo.example.com'];
        yield 'Site-ID 0' => ['0', 'https://matomo.example.com'];
        yield 'keine URL' => ['1', null];
        yield 'leere URL' => ['1', ''];
        yield 'relative URL' => ['1', '/matomo'];
        yield 'file-Schema' => ['1', 'file:///etc/passwd'];
        yield 'gopher-Schema' => ['1', 'gopher://matomo.example.com'];
    }
}
