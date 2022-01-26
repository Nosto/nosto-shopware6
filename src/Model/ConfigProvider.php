<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigProvider
{
    private SystemConfigService $systemConfig;

    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }
}
