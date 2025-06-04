<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Kernel;

class TestCase extends PhpUnitTestCase
{
    use IntegrationTestBehaviour;

    public function getName(bool $withDataSet = true): string
    {
        // PHPUnit 10+: use name()
        if (method_exists($this, 'name')) {
            return $this->name();
        }

        // PHPUnit 9 fallback
        return parent::getName($withDataSet);
    }

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
