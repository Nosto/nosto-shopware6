<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use Shopware\Core\Framework\Test\TestCaseBase\KernelTestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Kernel;

class TestCase extends KernelTestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
        Kernel::getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Kernel::getConnection()->rollBack();
    }
}
