<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

final class NostoCriteriaFactory
{
    private const PREFIX = 'Nosto.';

    private const DEFAULT_TITLE = 'criteria';

    public static function create(?string $title = null): Criteria
    {
        $criteria = new Criteria();
        self::setTitle($criteria, $title ?? self::DEFAULT_TITLE);

        return $criteria;
    }

    /**
     * @param list<string> $ids
     */
    public static function createWithIds(array $ids, ?string $title = null): Criteria
    {
        $criteria = new Criteria($ids);
        self::setTitle($criteria, $title ?? self::DEFAULT_TITLE);

        return $criteria;
    }

    public static function setTitle(Criteria $criteria, string $title): void
    {
        if (!str_starts_with($title, self::PREFIX)) {
            $title = self::PREFIX . $title;
        }

        $criteria->setTitle($title);
    }
}
