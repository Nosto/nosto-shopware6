<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\Connection;
use RuntimeException;

class QueuedJobIdProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $transportDsn,
    ) {
    }

    public function isAvailable(): bool
    {
        return str_starts_with($this->transportDsn, 'doctrine://');
    }

    /**
     * @return array<string, true>
     */
    public function getJobIds(): array
    {
        $jobIds = [];
        $bodies = $this->connection->iterateColumn(
            'SELECT `body` FROM `messenger_messages` WHERE `body` LIKE :needle OR `headers` LIKE :needle',
            [
                'needle' => '%NostoIntegration%Async%',
            ],
        );

        foreach ($bodies as $body) {
            preg_match_all('/"jobId"\s*:\s*"([0-9a-fA-F]{32})"/', (string) $body, $matches);
            if ($matches[1] === []) {
                throw new RuntimeException('Unable to read the job id of a queued Nosto message.');
            }
            foreach ($matches[1] as $jobId) {
                $jobIds[strtolower($jobId)] = true;
            }
        }

        return $jobIds;
    }
}
