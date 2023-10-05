<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Account;

use Nosto\Request\Api\Token;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Psr\Log\LoggerInterface;

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

    public function get(Context $context, string $channelId): ?Account
    {
        return array_values(array_filter($this->all($context), function(Account $account) use ($channelId) {
            return $account->getChannelId() === $channelId;
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
        $channels = $this->channelRepo->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $channel */
        foreach ($channels as $channel) {
            if (!$this->configProvider->isAccountEnabled($channel->getId())) {
                continue;
            }

            try {
                $accountName = $this->configProvider->getAccountName($channel->getId());
                $keyChain = new KeyChain([
                    new Token(Token::API_PRODUCTS, $this->configProvider->getProductToken($channel->getId())),
                    new Token(Token::API_EMAIL, $this->configProvider->getEmailToken($channel->getId())),
                    new Token(Token::API_GRAPHQL, $this->configProvider->getAppToken($channel->getId()))
                ]);
                $this->accounts[] = new Account($channel->getId(), $channel->getLanguageId(), $accountName, $keyChain);
            } catch (\Throwable $throwable) {
                $this->logger->error(
                    $throwable->getMessage(),
                    ContextHelper::createContextFromException($throwable)
                );
            }
        }

        if (empty($this->accounts)) {
            $this->accounts = [];
        }

        return $this->accounts;
    }
}
