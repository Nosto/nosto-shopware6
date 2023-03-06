<?php

namespace Od\NostoIntegration\Utils\RequestHelper;

use Nosto\Request\Http\HttpRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity;

class RequestHelper implements RequestHelperInterface
{
    protected EntityRepositoryInterface $pluginRepository;
    private string $swVersion;

    public function __construct(EntityRepositoryInterface $pluginRepository, string $version)
    {
        $this->pluginRepository = $pluginRepository;
        $this->swVersion = $version;
    }

    public function initUserAgent(Context $context): void
    {
        $plugin = $this->loadPlugin($context);
        if (!$plugin) {
            return;
        }
        HttpRequest::buildUserAgent('Shopware 6', $this->swVersion, $plugin->getVersion());
    }

    protected function loadPlugin(Context $context): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'overdose_nosto'));
        return $this->pluginRepository->search($criteria, $context)->first();
    }
}