<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\MockOperation\MockGraphQLOperation;
use Nosto\NostoIntegration\Model\MockOperation\MockSearchOperation;
use Nosto\NostoIntegration\Model\MockOperation\MockUpsertProduct;
use Nosto\Request\Api\Token as NostoToken;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class NostoMonitoringAuthService
{
    private const SESSION_AUTH_FLAG = 'authenticatedWithNosto';

    private const SESSION_AUTH_TIMESTAMP = 'nostoAuthTimestamp';

    private const AUTH_TIMEOUT = 3600; // 1 hour

    public function __construct(
        private readonly ConfigProvider $nostoConfigProvider,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $languageRepository,
    )
    {
    }

    public function validateAccessKey(string $accessKey, Context $context): bool
    {
        return $this->validateWithNostoApi($accessKey) && $this->validateFromApp($accessKey, $context);
    }

    public function authenticate(SessionInterface $session): void
    {
        $session->set(self::SESSION_AUTH_FLAG, true);
        $session->set(self::SESSION_AUTH_TIMESTAMP, time());
    }

    public function logout(SessionInterface $session): void
    {
        $session->remove(self::SESSION_AUTH_FLAG);
        $session->remove(self::SESSION_AUTH_TIMESTAMP);
    }

    public function isAuthenticated(SessionInterface $session): bool
    {
        if (!$session->get(self::SESSION_AUTH_FLAG)) {
            return false;
        }

        $authTimestamp = $session->get(self::SESSION_AUTH_TIMESTAMP);
        if (!$authTimestamp || (time() - $authTimestamp) > self::AUTH_TIMEOUT) {
            $this->logout($session);
            return false;
        }

        return true;
    }

    private function validateWithNostoApi(string $accessKey): bool
    {
        $account = new NostoSignupAccount('placeholder');
        $account->addApiToken(new NostoToken(NostoToken::API_GRAPHQL, $accessKey));

        return (new MockGraphQLOperation($account))->execute()['success'];
    }

    private function validateFromApp(string $accessKey, Context $context): bool
    {
        $criteria = new Criteria();

        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository->search($criteria, $context)->getEntities();
        foreach ($salesChannels as $salesChannel) {
            $salesChannelId = $salesChannel->getId();
            foreach ($languages as $language) {
                $languageId = $language->getId();
                /** @var SalesChannelEntity $salesChannel */
                $appToken = $this->nostoConfigProvider->getAppToken($salesChannelId, $languageId);

                if (!empty($appToken) && hash_equals($appToken, $accessKey)) {
                    return true;
                }
            }
        }

        return false;
    }
}
