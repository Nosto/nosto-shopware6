<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class FilterPayloadStore
{
    private const CACHE_KEY_PREFIX = 'nosto_filter_payload.';
    private const DEFAULT_TTL_SECONDS = 86400; // 1 day

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function store(string $payload, ?int $ttlSeconds = null): ?string
    {
        $token = bin2hex(random_bytes(16));

        try {
            $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $token);
            $item->set($payload);
            $item->expiresAfter($this->resolveTtl($ttlSeconds));
            $this->cache->save($item);

            return $token;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to store filter payload', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetch(?string $token): ?string
    {
        if (!$token) {
            return null;
        }

        try {
            $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $token);
            if (!$item->isHit()) {
                return null;
            }

            $payload = $item->get();

            return is_string($payload) ? $payload : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch filter payload', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveTtl(?int $ttlSeconds): int
    {
        if (is_int($ttlSeconds) && $ttlSeconds > 0) {
            return $ttlSeconds;
        }

        $envTtl = (int) getenv('NOSTO_FILTER_TOKEN_TTL');
        if ($envTtl > 0) {
            return $envTtl;
        }

        return self::DEFAULT_TTL_SECONDS;
    }
}
