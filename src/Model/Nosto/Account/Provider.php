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

    public function __construct(
        ConfigProvider $configProvider,
        EntityRepositoryInterface $channelRepo
    ) {
        $this->configProvider = $configProvider;
        $this->channelRepo = $channelRepo;
    }

    public function get(string $channelId): ?Account
    {
        return array_values(array_filter($this->all(), function(Account $account) use ($channelId) {
            return $account->getChannelId() === $channelId;
        }))[0] ?? null;
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
        $criteria->addFilter(new EqualsFilter('active', true));
        $context = Context::createDefaultContext();
        $channels = $this->channelRepo->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $channel */
        foreach ($channels as $channel) {
            $keyChain = new KeyChain([
                new Token(Token::API_PRODUCTS, $this->configProvider->getProductToken($channel->getId())),
                new Token(Token::API_EMAIL, $this->configProvider->getEmailToken($channel->getId())),
                new Token(Token::API_GRAPHQL, $this->configProvider->getAppToken($channel->getId()))
            ]);
            $this->accounts[] = new Account($channel->getId(), $channel->getLanguageId(), $keyChain);
        }

        return $this->accounts;
    }
}
