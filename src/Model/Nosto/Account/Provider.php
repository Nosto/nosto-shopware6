<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Account;

use Nosto\Request\Api\Token;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Account;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class Provider
{
    private ConfigProvider $configProvider;
    private EntityRepositoryInterface $channelRepo;
    private ?array $accounts = null;
     //TODO add API_EMAIL and API_APPS to config
    private const API_EMAIL = 'or3nWzPzH31r0zV1CNtLf5ugJ7bIRDsSdbZwX5DurBS9gddVzdXbL8VFSObuCd4d';
    private const API_APPS = 'DqDdEIqLzDRI1ozNfS6OtkLLCGyIYRxYoKKkD8dLSvYYF9fmbWIL1gEYadQb3m26';

    public function __construct(
        ConfigProvider $configProvider,
        EntityRepositoryInterface $channelRepo
    ) {
        $this->configProvider = $configProvider;
        $this->channelRepo = $channelRepo;
    }

    public function get(string $channelId)
    {
        return array_filter($this->all(), function(Account $account) use ($channelId) {
            return $account->getChannelId() === $channelId;
        })[0] ?? null;
    }

    /**
     * @return Account[]
     */
    public function all(): array
    {
        if ($this->accounts !== null) {
            return $this->accounts;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type.name', 'Storefront'));
        $context = Context::createDefaultContext();
        $channels = $this->channelRepo->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $channel */
        foreach ($channels as $channel) {
            $keyChain = new KeyChain([
                new Token(Token::API_PRODUCTS, $this->configProvider->getProductToken($channel->getId()))
            ]);
            $this->accounts[] = new Account($channel->getId(), $channel->getLanguageId(), $keyChain);
        }

        return $this->accounts;
    }
}
