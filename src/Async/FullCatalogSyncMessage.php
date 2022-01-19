<?php

namespace Od\NostoIntegration\Async;

use Od\NostoIntegration\Model\Operation\FullCatalogSyncHandler;
use Od\Scheduler\Async\JobMessageInterface;

class FullCatalogSyncMessage implements JobMessageInterface
{
    private string $jobId;

    /**
     * @param string $jobId
     */
    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getHandlerCode(): string
    {
        return FullCatalogSyncHandler::HANDLER_CODE;
    }
}