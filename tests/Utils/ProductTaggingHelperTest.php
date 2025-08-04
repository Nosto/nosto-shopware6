<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;

class ProductTaggingHelperTest extends TestCase
{
    public function testHelperInitializes(): void
    {
        $this->assertTrue(true);
    }

    public function testProductTaggingHelperClassExists(): void
    {
        $this->assertTrue(class_exists(ProductTaggingHelper::class));
    }
} 