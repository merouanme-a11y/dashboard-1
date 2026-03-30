<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ApiResultCacheService
{
    public function __construct(
        private CacheInterface $cache,
        private CacheItemPoolInterface $cachePool,
    ) {}

    public function remember(string $key, int $freshTtl, int $staleTtl, callable $resolver, bool $forceRefresh = false): array
    {
        $key = $this->normalizeKey($key);
        if ($forceRefresh) {
            $this->delete($key);
        }

        $cachedPayload = $this->peek($key);
        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $totalTtl = max(1, $freshTtl + max(0, $staleTtl));
        $envelope = $this->cache->get($key, function (ItemInterface $item) use ($resolver, $freshTtl, $staleTtl, $totalTtl): array {
            $item->expiresAfter($totalTtl);

            return $this->buildEnvelope((array) $resolver(), $freshTtl, $staleTtl);
        });

        if (!$this->isValidEnvelope($envelope)) {
            $this->delete($key);

            $envelope = $this->cache->get($key, function (ItemInterface $item) use ($resolver, $freshTtl, $staleTtl, $totalTtl): array {
                $item->expiresAfter($totalTtl);

                return $this->buildEnvelope((array) $resolver(), $freshTtl, $staleTtl);
            });
        }

        return $this->envelopeToPayload($envelope, time());
    }

    public function peek(string $key): ?array
    {
        $key = $this->normalizeKey($key);
        $item = $this->cachePool->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        $envelope = $item->get();
        if (!$this->isValidEnvelope($envelope)) {
            $this->delete($key);

            return null;
        }

        $now = time();
        if ((int) ($envelope['stale_until'] ?? 0) < $now) {
            $this->delete($key);

            return null;
        }

        return $this->envelopeToPayload($envelope, $now);
    }

    public function delete(string $key): void
    {
        $key = $this->normalizeKey($key);
        $this->cache->delete($key);
        $this->cachePool->deleteItem($key);
    }

    private function buildEnvelope(array $payload, int $freshTtl, int $staleTtl): array
    {
        $generatedAt = time();
        $freshUntil = $generatedAt + max(0, $freshTtl);
        $staleUntil = $freshUntil + max(0, $staleTtl);

        return [
            'generated_at' => $generatedAt,
            'fresh_until' => $freshUntil,
            'stale_until' => $staleUntil,
            'payload' => $payload,
        ];
    }

    private function envelopeToPayload(mixed $envelope, int $now): array
    {
        if (!$this->isValidEnvelope($envelope)) {
            return ['_error' => 'Entree de cache API invalide.'];
        }

        $payload = (array) ($envelope['payload'] ?? []);
        $freshUntil = (int) ($envelope['fresh_until'] ?? $now);
        $staleUntil = (int) ($envelope['stale_until'] ?? $freshUntil);
        $generatedAt = (int) ($envelope['generated_at'] ?? $now);
        $isStale = $freshUntil < $now;

        $payload['_cache'] = [
            'source' => 'server',
            'state' => $isStale ? 'stale' : 'fresh',
            'isStale' => $isStale,
            'generatedAt' => gmdate(DATE_ATOM, $generatedAt),
            'freshUntil' => gmdate(DATE_ATOM, $freshUntil),
            'staleUntil' => gmdate(DATE_ATOM, $staleUntil),
        ];

        return $payload;
    }

    private function isValidEnvelope(mixed $envelope): bool
    {
        if (!is_array($envelope)) {
            return false;
        }

        if (!isset($envelope['generated_at'], $envelope['fresh_until'], $envelope['stale_until'])) {
            return false;
        }

        if (!is_array($envelope['payload'] ?? null)) {
            return false;
        }

        return true;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new \InvalidArgumentException('La cle de cache API est obligatoire.');
        }

        return $key;
    }
}
