<?php

namespace App\Tests\Service;

use App\Service\ApiResultCacheService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ApiResultCacheServiceTest extends TestCase
{
    public function testRememberReturnsFreshPayloadWithoutRecomputingWithinFreshWindow(): void
    {
        $adapter = new ArrayAdapter();
        $service = new ApiResultCacheService($adapter, $adapter);
        $resolverCalls = 0;

        $firstPayload = $service->remember('api_result_cache_test_fresh', 60, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'first'];
        });

        $secondPayload = $service->remember('api_result_cache_test_fresh', 60, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'second'];
        });

        self::assertSame(1, $resolverCalls);
        self::assertSame('first', $firstPayload['value']);
        self::assertSame('first', $secondPayload['value']);
        self::assertSame('fresh', $secondPayload['_cache']['state'] ?? null);
    }

    public function testRememberReusesStalePayloadBeforeTotalExpiration(): void
    {
        $adapter = new ArrayAdapter();
        $service = new ApiResultCacheService($adapter, $adapter);
        $resolverCalls = 0;

        $firstPayload = $service->remember('api_result_cache_test_stale', 1, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'stale-value'];
        });

        sleep(2);

        $secondPayload = $service->remember('api_result_cache_test_stale', 1, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'new-value'];
        });

        self::assertSame(1, $resolverCalls);
        self::assertSame('stale-value', $firstPayload['value']);
        self::assertSame('stale-value', $secondPayload['value']);
        self::assertTrue((bool) ($secondPayload['_cache']['isStale'] ?? false));
    }

    public function testForceRefreshDeletesExistingEntryBeforeResolving(): void
    {
        $adapter = new ArrayAdapter();
        $service = new ApiResultCacheService($adapter, $adapter);
        $resolverCalls = 0;

        $firstPayload = $service->remember('api_result_cache_test_refresh', 60, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'first'];
        });

        $secondPayload = $service->remember('api_result_cache_test_refresh', 60, 300, function () use (&$resolverCalls): array {
            ++$resolverCalls;

            return ['value' => 'second'];
        }, true);

        self::assertSame(2, $resolverCalls);
        self::assertSame('first', $firstPayload['value']);
        self::assertSame('second', $secondPayload['value']);
    }
}
