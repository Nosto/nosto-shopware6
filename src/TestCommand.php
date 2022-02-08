<?php declare(strict_types=1);

namespace Od\NostoIntegration;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
use Od\NostoIntegration\Async\OrderSyncMessage;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Model\Operation\OrderSyncHandler;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO: delete this before release
class TestCommand extends Command
{
    protected static $defaultName = 'od:nosto:test';

    private JobScheduler $jobScheduler;

    public function __construct(
        JobScheduler $jobScheduler
    ) {
        parent::__construct();
        $this->jobScheduler = $jobScheduler;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
//        $jobMessage = new FullCatalogSyncMessage(Uuid::randomHex());
//        $this->jobScheduler->schedule($jobMessage);
        $jobMessage = new OrderSyncMessage(Uuid::randomHex(), '', ['30162DACBD214D939A1919C41A3FBE6D'],
            ['F9C1B8DEC5DE4FCBAB8EAF25482C3824']);
//        $jobMessage = new ProductSyncMessage(Uuid::randomHex(),'',['c7bca22753c84d08b6178a50052b4146']);
        $this->jobScheduler->schedule($jobMessage);

        return 0;
    }
}
