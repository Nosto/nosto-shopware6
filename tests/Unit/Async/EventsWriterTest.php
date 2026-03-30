<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Async;

use Nosto\NostoIntegration\Async\EventsWriter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class EventsWriterTest extends TestCase
{
    public function testWriteEventCreatesChangelogEntryWhenItDoesNotExistYet(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $searchResult = new IdSearchResult(
            0,
            [],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $repository->expects($this->once())
            ->method('searchIds')
            ->willReturn($searchResult);
        $repository->expects($this->once())
            ->method('create')
            ->with([
                [
                    'entityType' => EventsWriter::PRODUCT_ENTITY_NAME,
                    'entityId' => 'product-id',
                    'productNumber' => 'product-number',
                ],
            ]);

        $writer = new EventsWriter($repository);
        $writer->writeEvent(
            EventsWriter::PRODUCT_ENTITY_NAME,
            'product-id',
            Context::createDefaultContext(),
            'product-number',
        );
    }

    public function testWriteEventSkipsDuplicateChangelogEntries(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $searchResult = new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => 'existing-id',
                    'data' => [],
                ],
            ],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $repository->expects($this->once())
            ->method('searchIds')
            ->willReturn($searchResult);
        $repository->expects($this->never())
            ->method('create');

        $writer = new EventsWriter($repository);
        $writer->writeEvent(
            EventsWriter::PRODUCT_ENTITY_NAME,
            'product-id',
            Context::createDefaultContext(),
            'product-number',
        );
    }
}
