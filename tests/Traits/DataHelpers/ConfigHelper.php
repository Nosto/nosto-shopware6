<?php

namespace Nosto\NostoIntegration\Tests\Traits\DataHelpers;

use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Defaults;

trait ConfigHelper
{
    private function getDefaultNostoConfigServiceMock(array $overrides = []): NostoConfigService {
        /** @var NostoConfigService|MockObject $configServiceMock */
        $configServiceMock = $this->createMock(NostoConfigService::class);

        $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
        $languageId = Defaults::LANGUAGE_SYSTEM;
        if (isset($overrides['salesChannelId'])) {
            $salesChannelId = $overrides['salesChannelId'];
            unset($overrides['salesChannelId']);
        }
        if (isset($overrides['languageId'])) {
            $languageId = $overrides['languageId'];
            unset($overrides['languageId']);
        }

        $defaultConfig = [
            'isEnabled' => true,
            'accountID' => 'shopware-12345',
            'accountName' => 'Real account',
            'searchToken' => 'search-secret',
            'enableSearch' => true,
            'enableNavigation' => true,
        ];

        $config = array_merge($defaultConfig, $overrides);

        $returnMap = [];
        foreach ($config as $configName => $configValue) {
            $returnMap[] = [
                $configName,
                $salesChannelId,
                $languageId,
                $configValue
            ];
        }

        $configServiceMock->method('get')
            ->willReturnMap($returnMap);
        $configServiceMock->method('getBool')
            ->willReturnMap($returnMap);
        $configServiceMock->method('getString')
            ->willReturnMap($returnMap);
        $configServiceMock->method('getInt')
            ->willReturnMap($returnMap);
        $configServiceMock->method('getFloat')
            ->willReturnMap($returnMap);
        $configServiceMock->method('getConfigWithInheritance')
            ->willReturn($config);

        return $configServiceMock;
    }
}
