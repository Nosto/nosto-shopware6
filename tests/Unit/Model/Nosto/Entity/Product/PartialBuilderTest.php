<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Nosto\Entity\Product;

use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Contracts\Service\ResetInterface;

final class PartialBuilderTest extends TestCase
{
    public function testResetClearsAllInMemoryCaches(): void
    {
        $reflection = new ReflectionClass(PartialBuilder::class);
        $builder = $reflection->newInstanceWithoutConstructor();
        $cacheProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PRIVATE),
            static function (ReflectionProperty $property): bool {
                $type = $property->getType();

                return str_ends_with($property->getName(), 'Cache')
                    && $type instanceof ReflectionNamedType
                    && $type->getName() === 'array';
            },
        );
        self::assertNotEmpty($cacheProperties);

        foreach ($cacheProperties as $property) {
            $property->setValue($builder, ['cached-value']);
        }

        self::assertInstanceOf(ResetInterface::class, $builder);

        $builder->reset();

        foreach ($cacheProperties as $property) {
            self::assertSame([], $property->getValue($builder));
        }
    }
}
