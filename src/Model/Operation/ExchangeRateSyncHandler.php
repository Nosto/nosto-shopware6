<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Model\ExchangeRate;
use Nosto\Model\ExchangeRateCollection;
use Nosto\NostoIntegration\Async\ExchangeRateSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Operation\SyncRates;
use Nosto\Scheduler\Model\Job\JobHandlerInterface;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\Job\Message\InfoMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Currency\CurrencyEntity;

class ExchangeRateSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-exchange-rate-sync';

    public function __construct(
        private readonly EntityRepository $currencyRepository,
        private readonly AccountProvider $accountProvider,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param ExchangeRateSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $context = $message->getContext();
        $result = new JobResult();
        $shouldLogExtra = $this->configProvider->isEnabledProductSyncExtraLogging();
        $syncStartedAt = $shouldLogExtra ? microtime(true) : null;

        $rates = $this->buildExchangeRateCollection($context);
        if (empty($rates->getRates())) {
            $result->addMessage(new InfoMessage('No exchange rates found, skipping sync.'));
            return $result;
        }

        foreach ($this->accountProvider->all($context) as $account) {
            if (!$this->configProvider->isEnabledMultiCurrency($account->getChannelId(), $account->getLanguageId())) {
                $result->addMessage(new InfoMessage(
                    sprintf(
                        'Exchange rate sync skipped for account %s (multi-currency disabled).',
                        $account->getNostoAccount()->getName(),
                    ),
                ));
                continue;
            }

            try {
                $operation = new SyncRates($account->getNostoAccount());
                $operation->update($rates);
                $result->addMessage(new InfoMessage(
                    sprintf(
                        'Exchange rates synced for account %s.',
                        $account->getNostoAccount()->getName(),
                    ),
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Exchange rate sync failed: ' . $e->getMessage(), [
                    'sales_channel_id' => $account->getChannelId(),
                    'language_id' => $account->getLanguageId(),
                ]);
                $result->addError($e);
            }
        }

        if ($shouldLogExtra && $syncStartedAt !== null) {
            $durationMs = (microtime(true) - $syncStartedAt) * 1000;
            $this->logger->info('product_sync.exchange_rates.execute', [
                'duration_ms' => round($durationMs, 2),
                'language_id' => $context->getLanguageId(),
                'currency_id' => $context->getCurrencyId(),
                'rate_count' => count($rates->getRates()),
            ]);
        }

        return $result;
    }

    private function buildExchangeRateCollection(Context $context): ExchangeRateCollection
    {
        $collection = new ExchangeRateCollection();
        $criteria = NostoCriteriaFactory::create('exchange_rates.sync');
        $currencies = $this->currencyRepository->search($criteria, $context)->getEntities();

        /** @var CurrencyEntity $currency */
        foreach ($currencies as $currency) {
            $isoCode = $currency->getIsoCode();
            $factor = $currency->getFactor();

            if (!$isoCode || $factor === null) {
                continue;
            }

            $collection->addRate($isoCode, new ExchangeRate($isoCode, (string) $factor));
        }

        return $collection;
    }
}
