<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProductSyncHandlerTest extends TestCase
{
    #[DataProvider('provideValidateProductWarnings')]
    public function testValidateProductReturnsWarningsForMissingMandatoryFields(
        ?string $name,
        ?string $url,
        ?string $imageUrl,
        string $expectedMessage,
    ): void {
        $handler = $this->createHandler();
        $product = new NostoProduct();

        if ($name !== null) {
            $product->setName($name);
        }

        if ($url !== null) {
            $product->setUrl($url);
        }

        if ($imageUrl !== null) {
            $product->setImageUrl($imageUrl);
        }

        $result = $handler->validateProductPublic('SWDEMO10002', $product);

        self::assertInstanceOf(WarningMessage::class, $result);
        self::assertSame($expectedMessage, $result->getMessage());
    }

    public function testValidateProductReturnsNullWhenMandatoryFieldsArePresent(): void
    {
        $handler = $this->createHandler();
        $product = new NostoProduct();
        $product->setName('Example product');
        $product->setUrl('https://shop.example.test/product/example');
        $product->setImageUrl('https://cdn.example.test/product/example.jpg');

        self::assertNull($handler->validateProductPublic('SWDEMO10002', $product));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: string|null, 3: string}>
     */
    public static function provideValidateProductWarnings(): iterable
    {
        yield 'missing image url' => [
            'Example product',
            'https://shop.example.test/product/example',
            null,
            'Product image url is empty, ignoring upsert for product with number. SWDEMO10002',
        ];

        yield 'missing url' => [
            'Example product',
            null,
            'https://cdn.example.test/product/example.jpg',
            'Product url is empty, ignoring upsert for product with number. SWDEMO10002',
        ];

        yield 'missing name' => [
            null,
            'https://shop.example.test/product/example',
            'https://cdn.example.test/product/example.jpg',
            'Product name is empty, ignoring upsert for product with number. SWDEMO10002',
        ];

        yield 'missing all mandatory fields' => [
            null,
            null,
            null,
            'Product image url is empty, Product url is empty, Product name is empty, ignoring upsert for product with number. SWDEMO10002',
        ];
    }

    private function createHandler(): TestableProductSyncHandler
    {
        return new TestableProductSyncHandler(
            $this->createMock(AbstractSalesChannelContextFactory::class),
            $this->createMock(PartialProvider::class),
            $this->createMock(AccountProvider::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ConfigProvider::class),
            $this->createMock(AbstractRuleLoader::class),
            $this->createMock(ProductHelper::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}

final class TestableProductSyncHandler extends ProductSyncHandler
{
    public function validateProductPublic(string $productNumber, NostoProduct $product): ?WarningMessage
    {
        return $this->validateProduct($productNumber, $product);
    }
}
