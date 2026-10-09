<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Doctrine\DBAL\Connection;
use RuntimeException;

class QueuedJobIdProvider
{
    private const FAILED_QUEUE = 'failed';

    private const JOB_MESSAGE_NAMESPACE = 'Nosto\\NostoIntegration\\Async\\';

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
        $messages = $this->connection->iterateAssociative(
            'SELECT `headers`, `body` FROM `messenger_messages` '
            . 'WHERE `queue_name` <> :failedQueue AND `headers` LIKE :needle',
            [
                'failedQueue' => self::FAILED_QUEUE,
                'needle' => '%NostoIntegration%',
            ],
        );

        foreach ($messages as $message) {
            if (!$this->isJobMessage((string) $message['headers'])) {
                continue;
            }

            preg_match_all('/"jobId"\s*:\s*"([0-9a-fA-F]{32})"/', (string) $message['body'], $matches);
            if ($matches[1] === []) {
                throw new RuntimeException('Unable to read the job id of a queued Nosto message.');
            }
            foreach ($matches[1] as $jobId) {
                $jobIds[strtolower($jobId)] = true;
            }
        }

        return $jobIds;
    }

    private function isJobMessage(string $headers): bool
    {
        $decodedHeaders = json_decode($headers, true);
        $type = is_array($decodedHeaders) ? ($decodedHeaders['type'] ?? null) : null;

        return is_string($type) && str_starts_with($type, self::JOB_MESSAGE_NAMESPACE);
    }
}
