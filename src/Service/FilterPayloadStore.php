<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use DateTimeImmutable;
use DateTimeZone;
use Nosto\NostoIntegration\Entity\FilterPayload\FilterPayloadEntity;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class FilterPayloadStore
{
    private const DEFAULT_TTL_SECONDS = 86400; // 1 day

    private const CLEANUP_BATCH_SIZE = 1000;

    public function __construct(
        private readonly EntityRepository $filterPayloadRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function store(string $payload, ?int $ttlSeconds = null): ?string
    {
        try {
            $token = bin2hex(random_bytes(16));
            $now = $this->now();
            $expiresAt = $now->modify(sprintf('+%d seconds', $this->resolveTtl($ttlSeconds)));

            $this->filterPayloadRepository->create([
                [
                    'id' => Uuid::randomHex(),
                    'token' => $token,
                    'payload' => $payload,
                    'expiresAt' => $this->formatDateTime($expiresAt),
                    'createdAt' => $this->formatDateTime($now),
                ],
            ], $this->systemContext());

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
            $criteria = NostoCriteriaFactory::create('filter_payload_store.fetch');
            $criteria->addFilter(
                new EqualsFilter('token', $token),
                new RangeFilter('expiresAt', [
                    RangeFilter::GT => $this->formatDateTime($this->now()),
                ]),
            );
            $criteria->setLimit(1);

            $payload = $this->filterPayloadRepository
                ->search($criteria, $this->systemContext())
                ->first();

            if (!$payload instanceof FilterPayloadEntity) {
                return null;
            }

            return $payload->getPayload();
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

    public function deleteExpiredPayloads(?Context $context = null): int
    {
        $context ??= $this->systemContext();
        $deleted = 0;

        do {
            $criteria = NostoCriteriaFactory::create('filter_payload.store.deleteExpiredPayloads');
            $criteria->addFilter(
                new RangeFilter('expiresAt', [
                    RangeFilter::LTE => $this->formatDateTime($this->now()),
                ]),
            );
            $criteria->setLimit(self::CLEANUP_BATCH_SIZE);

            $ids = $this->filterPayloadRepository->searchIds($criteria, $context)->getIds();
            if ($ids === []) {
                return $deleted;
            }

            $this->filterPayloadRepository->delete(
                array_map(static fn (string $id): array => [
                    'id' => $id,
                ], $ids),
                $context,
            );

            $deleted += count($ids);
        } while (count($ids) === self::CLEANUP_BATCH_SIZE);

        return $deleted;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    private function systemContext(): Context
    {
        return new Context(new SystemSource());
    }
}
