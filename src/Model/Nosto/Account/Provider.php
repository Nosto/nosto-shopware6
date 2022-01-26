<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Account;

use Nosto\Request\Api\Token;
use Od\NostoIntegration\Model\Nosto\Account;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class Provider
{
    private EntityRepositoryInterface $channelRepo;
    private array $accounts = [];

    public function __construct(EntityRepositoryInterface $channelRepo)
    {
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

        $key = 'in5aaqAawFPNGR0sqnn1L0LkIhzf5cVs8y1VXR2awpcyL3npflChsq1Z6JJG3p1I';
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type.name', 'Storefront'));
        $context = Context::createDefaultContext();
        $channels = $this->channelRepo->search($criteria, $context)->getEntities();

        /** @var SalesChannelEntity $channel */
        foreach ($channels as $channel) {
            $keyChain = new KeyChain([new Token(Token::API_PRODUCTS, $key)]);
            $this->accounts[] = new Account($channel->getId(), $channel->getLanguageId(), $keyChain);
        }

        return $this->accounts;
    }
}
