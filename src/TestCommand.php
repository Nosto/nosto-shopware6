<?php declare(strict_types=1);

namespace Od\NostoIntegration;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
use Od\NostoIntegration\Async\OrderSyncMessage;
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
    private OrderSyncHandler $orderSyncHandler;

    public function __construct(
        JobScheduler $jobScheduler,
        OrderSyncHandler $orderSyncHandler
    ) {
        parent::__construct();
        $this->jobScheduler = $jobScheduler;
        $this->orderSyncHandler = $orderSyncHandler;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
//        $jobMessage = new FullCatalogSyncMessage(Uuid::randomHex());
//        $this->jobScheduler->schedule($jobMessage);
            $jobMessage = new OrderSyncMessage(Uuid::randomHex(),'',['68D9B6E7764148EBB93756E94646DC76']);
            $this->jobScheduler->schedule($jobMessage);
        return 0;
    }
}
