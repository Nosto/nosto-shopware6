<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Account;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Nosto\Request\Api\Token;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class Provider
{
    private ConfigProvider $configProvider;

    private EntityRepository $channelRepo;

    private ?array $accounts = null;

    private LoggerInterface $logger;

    public function __construct(
        ConfigProvider $configProvider,
        EntityRepository $channelRepo,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->channelRepo = $channelRepo;
        $this->logger = $logger;
    }

    public function get(Context $context, string $channelId, string $languageId): ?Account
    {
        return array_values(array_filter($this->all($context), function (Account $account) use (
            $channelId,
            $languageId
        ) {
            return $account->getChannelId() === $channelId && $account->getLanguageId() === $languageId;
        }))[0] ?? null;
    }

    /**
     * @return Account[]
     */
    public function all(Context $context): array
    {
        if ($this->accounts !== null) {
            return $this->accounts;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type.name', 'Storefront'));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('languages');

        $channels = $this->channelRepo->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $channel */
        foreach ($channels as $channel) {
            $channelId = $channel->getId();

            foreach ($channel->getLanguages() as $language) {
                $languageId = $language->getId();

                if (!$this->configProvider->isAccountEnabled($channelId, $languageId)) {
                    continue;
                }

                try {
                    $accountName = $this->configProvider->getAccountName($channelId, $languageId);
                    $keyChain = new KeyChain([
                        new Token(Token::API_PRODUCTS, $this->configProvider->getProductToken($channelId, $languageId)),
                        new Token(Token::API_EMAIL, $this->configProvider->getEmailToken($channelId, $languageId)),
                        new Token(Token::API_GRAPHQL, $this->configProvider->getAppToken($channelId, $languageId)),
                    ]);
                    $this->accounts[] = new Account($channelId, $languageId, $accountName, $keyChain);
                } catch (\Throwable $throwable) {
                    $this->logger->error(
                        $throwable->getMessage(),
                        ContextHelper::createContextFromException($throwable)
                    );
                }
            }
        }

        if (empty($this->accounts)) {
            $this->accounts = [];
        }

        return $this->accounts;
    }
}
