<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\Connection;

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
            'SELECT `body` FROM `messenger_messages` WHERE `body` LIKE :needle',
            [
                'needle' => '%Nosto%',
            ],
        );

        foreach ($bodies as $body) {
            preg_match_all('/[0-9a-f]{32}/i', (string) $body, $matches);
            foreach ($matches[0] as $jobId) {
                $jobIds[strtolower($jobId)] = true;
            }
        }

        return $jobIds;
    }
}
