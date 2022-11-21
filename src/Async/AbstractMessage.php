<?php

namespace Od\NostoIntegration\Async;

use Od\Scheduler\Async\JobMessageInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

abstract class AbstractMessage implements JobMessageInterface
{
    private string $jobId;
    private string $name;
    protected static string $defaultName = 'Unnamed Operation';
    protected ?Context $context;

    public function __construct(
        string $jobId,
        ?Context $context = null,
        ?string $name = null
    ) {
        $this->jobId = $jobId;
        $this->name = $name ?? static::$defaultName;
        $this->setContext($context);
    }

    protected function setContext(?Context $context) {
        // All values should be sent to nosto with the default currency and language
        if ($context &&
            ($context->getLanguageId() !== Defaults::LANGUAGE_SYSTEM ||
                $context->getCurrencyId() !== Defaults::CURRENCY
            )
        ) {
            $context = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                Defaults::CURRENCY,
                [Defaults::LANGUAGE_SYSTEM],
                $context->getVersionId(),
                $context->getCurrencyFactor(),
                $context->considerInheritance(),
                $context->getTaxState(),
                $context->getRounding()
            );
        }
        $this->context = $context;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getJobName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context ?: self::createDefaultContext();
    }

    public static function createDefaultContext(): Context
    {
        return new Context(new SystemSource());
    }


}
