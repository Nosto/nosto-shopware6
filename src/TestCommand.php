<?php declare(strict_types=1);

namespace Od\NostoIntegration;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
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
        $jobMessage = new FullCatalogSyncMessage(Uuid::randomHex());
        $this->jobScheduler->schedule($jobMessage);

        return 0;
    }
}