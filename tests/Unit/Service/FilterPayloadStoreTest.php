<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service;

use Nosto\NostoIntegration\Entity\FilterPayload\FilterPayloadCollection;
use Nosto\NostoIntegration\Entity\FilterPayload\FilterPayloadDefinition;
use Nosto\NostoIntegration\Entity\FilterPayload\FilterPayloadEntity;
use Nosto\NostoIntegration\Service\FilterPayloadStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class FilterPayloadStoreTest extends TestCase
{
    public function testStoreCreatesFilterPayloadEntity(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(static function (array $data): bool {
                    $payload = $data[0] ?? [];

                    return is_string($payload['id'] ?? null)
                        && is_string($payload['token'] ?? null)
                        && preg_match('/^[a-f0-9]{32}$/', $payload['token']) === 1
                        && ($payload['payload'] ?? null) === '{"filters":["a"]}'
                        && is_string($payload['expiresAt'] ?? null)
                        && is_string($payload['createdAt'] ?? null);
                }),
                self::isInstanceOf(Context::class),
            );

        $token = (new FilterPayloadStore($repository, new NullLogger()))->store('{"filters":["a"]}', 60);

        self::assertIsString($token);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
    }

    public function testFetchReturnsPayloadFromRepository(): void
    {
        $entity = new FilterPayloadEntity();
        $entity->setId('018f8f07686573a4b9e3d34d36906a8a');
        $entity->setToken('token');
        $entity->setPayload('{"filters":["a"]}');

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->with(
                self::isInstanceOf(Criteria::class),
                self::isInstanceOf(Context::class),
            )
            ->willReturn($this->createSearchResult([$entity]));

        self::assertSame(
            '{"filters":["a"]}',
            (new FilterPayloadStore($repository, new NullLogger()))->fetch('token'),
        );
    }

    public function testFetchReturnsNullWhenRepositoryFindsNothing(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([]));

        self::assertNull((new FilterPayloadStore($repository, new NullLogger()))->fetch('token'));
    }

    public function testDeleteExpiredPayloadsDeletesFoundIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('searchIds')
            ->with(
                self::isInstanceOf(Criteria::class),
                self::isInstanceOf(Context::class),
            )
            ->willReturn($this->createIdSearchResult(['018f8f07686573a4b9e3d34d36906a8a']));
        $repository
            ->expects(self::once())
            ->method('delete')
            ->with(
                [['id' => '018f8f07686573a4b9e3d34d36906a8a']],
                self::isInstanceOf(Context::class),
            );

        self::assertSame(
            1,
            (new FilterPayloadStore($repository, new NullLogger()))->deleteExpiredPayloads(),
        );
    }

    /**
     * @param list<FilterPayloadEntity> $entities
     */
    private function createSearchResult(array $entities): EntitySearchResult
    {
        $criteria = new Criteria();
        $context = new Context(new SystemSource());

        return new EntitySearchResult(
            FilterPayloadDefinition::ENTITY_NAME,
            count($entities),
            new FilterPayloadCollection($entities),
            null,
            $criteria,
            $context,
        );
    }

    /**
     * @param list<string> $ids
     */
    private function createIdSearchResult(array $ids): IdSearchResult
    {
        $data = array_map(static fn (string $id): array => [
            'primaryKey' => $id,
            'data' => ['id' => $id],
        ], $ids);

        return new IdSearchResult(
            count($ids),
            $data,
            new Criteria(),
            new Context(new SystemSource()),
        );
    }
}
