<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\EventListener;

use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\EventListener\CategoryWrittenEvent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class CategoryWrittenEventTest extends TestCase
{
    public function testWritesCategoryChangelogEvent(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->method('getIds')->willReturn([$categoryId]);
        $event->method('getEntityName')->willReturn(CategoryDefinition::ENTITY_NAME);
        $event->method('getContext')->willReturn($context);

        $calls = [];
        $listener = new CategoryWrittenEvent(
            $this->recordingEventsWriter($calls),
            $this->createMock(EntityRepository::class),
        );

        $listener->onCategoryWritten($event);

        self::assertSame([
            [CategoryDefinition::ENTITY_NAME, $categoryId, $context],
        ], $calls);
    }

    public function testWritesPreviousAndCurrentStreamsAfterSuccessfulCategoryWrite(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $previousStreamId = Uuid::randomHex();
        $currentStreamId = Uuid::randomHex();
        $success = null;
        $event = $this->writeEvent($categoryId, $context, $success);
        $repository = $this->categoryRepository([
            [$previousStreamId],
            [$currentStreamId],
        ], $categoryId, $context);
        $calls = [];
        $listener = new CategoryWrittenEvent($this->recordingEventsWriter($calls), $repository);

        $listener->beforeCategoryWrite($event);

        self::assertSame([], $calls);
        self::assertNotNull($success);
        $success();

        self::assertSame([
            [EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME, $previousStreamId, $context],
            [EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME, $currentStreamId, $context],
        ], $calls);
    }

    public function testWritesPreviousStreamWhenCategoryBecomesStatic(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $previousStreamId = Uuid::randomHex();
        $success = null;
        $event = $this->writeEvent($categoryId, $context, $success);
        $repository = $this->categoryRepository([
            [$previousStreamId],
            [],
        ], $categoryId, $context);
        $calls = [];
        $listener = new CategoryWrittenEvent($this->recordingEventsWriter($calls), $repository);

        $listener->beforeCategoryWrite($event);
        self::assertNotNull($success);
        $success();

        self::assertSame([
            [EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME, $previousStreamId, $context],
        ], $calls);
    }

    public function testDeduplicatesUnchangedStreamWhenCategoryMoves(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $success = null;
        $event = $this->writeEvent($categoryId, $context, $success);
        $repository = $this->categoryRepository([
            [$streamId],
            [$streamId],
        ], $categoryId, $context);
        $calls = [];
        $listener = new CategoryWrittenEvent($this->recordingEventsWriter($calls), $repository);

        $listener->beforeCategoryWrite($event);
        self::assertNotNull($success);
        $success();

        self::assertSame([
            [EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME, $streamId, $context],
        ], $calls);
    }

    /**
     * @param callable(): void|null $success
     */
    private function writeEvent(string $categoryId, Context $context, ?callable &$success): EntityWriteEvent
    {
        $event = $this->createMock(EntityWriteEvent::class);
        $event->method('getIds')->with(CategoryDefinition::ENTITY_NAME)->willReturn([$categoryId]);
        $event->method('getContext')->willReturn($context);
        $event->method('addSuccess')->willReturnCallback(static function (callable $callback) use (&$success): void {
            $success = $callback;
        });

        return $event;
    }

    /**
     * @param list<list<string>> $streamIdsBySearch
     */
    private function categoryRepository(
        array $streamIdsBySearch,
        string $categoryId,
        Context $context,
    ): EntityRepository {
        $repository = $this->createMock(EntityRepository::class);
        $searchIndex = 0;
        $repository->expects(self::exactly(count($streamIdsBySearch)))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria, Context $callContext) use (
                &$searchIndex,
                $streamIdsBySearch,
                $categoryId,
                $context,
            ): EntitySearchResult {
                self::assertSame([$categoryId], $criteria->getIds());
                self::assertSame($context, $callContext);

                $categories = new CategoryCollection();
                foreach ($streamIdsBySearch[$searchIndex++] as $streamId) {
                    $category = new CategoryEntity();
                    $category->setId(Uuid::randomHex());
                    $category->setProductStreamId($streamId);
                    $categories->add($category);
                }

                return new EntitySearchResult(
                    CategoryDefinition::ENTITY_NAME,
                    $categories->count(),
                    $categories,
                    new AggregationResultCollection(),
                    $criteria,
                    $callContext,
                );
            });

        return $repository;
    }

    /**
     * @param list<array{string, string, Context}> $calls
     */
    private function recordingEventsWriter(array &$calls): EventsWriter
    {
        $eventsWriter = $this->createMock(EventsWriter::class);
        $eventsWriter->method('writeEvent')->willReturnCallback(
            static function (
                string $entityName,
                string $entityId,
                Context $context,
            ) use (&$calls): void {
                $calls[] = [$entityName, $entityId, $context];
            },
        );

        return $eventsWriter;
    }
}
