<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service;

use Nosto\Operation\Recommendation\CategoryMerchandising;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryMerchandisingProvider implements SalesChannelRepositoryInterface
{
    private SalesChannelRepositoryInterface $repository;
    private Provider $accountProvider;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        Provider $accountProvider
    ) {
        $this->repository = $repository;
        $this->accountProvider = $accountProvider;
    }

    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        return $this->repository->search($criteria, $salesChannelContext);
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        return $this->repository->aggregate($criteria, $salesChannelContext);
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $account = $this->accountProvider->get($salesChannelContext->getSalesChannelId());
        if (!$account) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }
        try {
            $operation = new CategoryMerchandising(
                $account->getNostoAccount(),
                //TODO: get customerId from cookies
                '61fb9eebc22626222ff7f48b',
                //TODO: get category
                '/clothing',
                $criteria->getLimit(),
                //TODO: add FiltersTranslator for filters
                new IncludeFilters(),
                new ExcludeFilters()
            );

            $result = $operation->execute();
            $converted = [
                'e1d383c700cc4b8eaf9786b4bcb98364' => [
                    'primaryKey' => 'e1d383c700cc4b8eaf9786b4bcb98364',
                    'data' => ['id' => 'e1d383c700cc4b8eaf9786b4bcb98364']
                ],
                'd29d7c9c5e0745de9ab4fcdc6f2338b8' => [
                    'primaryKey' => 'd29d7c9c5e0745de9ab4fcdc6f2338b8',
                    'data' => ['id' => 'd29d7c9c5e0745de9ab4fcdc6f2338b8']
                ],
                '99a7e5f8edf54970a3f19cf21cb387f9' => [
                    'primaryKey' => '99a7e5f8edf54970a3f19cf21cb387f9',
                    'data' => ['id' => '99a7e5f8edf54970a3f19cf21cb387f9']
                ],
                '180edfb7ded148e888a428cf2dfbd073' => [
                    'primaryKey' => '180edfb7ded148e888a428cf2dfbd073',
                    'data' => ['id' => '180edfb7ded148e888a428cf2dfbd073']
                ],
//            '85dbf55327f9491f81ebd3ed035433ee' => [
//                'primaryKey' => '85dbf55327f9491f81ebd3ed035433ee',
//                'data' => ['id' => '85dbf55327f9491f81ebd3ed035433ee']
//            ],
//            '8567366730b446c5ac7030b051e0df2b' => [
//                'primaryKey' => '8567366730b446c5ac7030b051e0df2b',
//                'data' => ['id' => '8567366730b446c5ac7030b051e0df2b']
//            ],
//            '45cd160030db407a9bbb8a52862a205a' => [
//                'primaryKey' => '45cd160030db407a9bbb8a52862a205a',
//                'data' => ['id' => '45cd160030db407a9bbb8a52862a205a']
//            ],
//            '1d6361cb8def49d89037a732484ff464' => [
//                'primaryKey' => '1d6361cb8def49d89037a732484ff464',
//                'data' => ['id' => '1d6361cb8def49d89037a732484ff464']
//            ],
//            'd5122e71516f4f2cbd11907aa86d3ddd' => [
//                'primaryKey' => 'd5122e71516f4f2cbd11907aa86d3ddd',
//                'data' => ['id' => 'd5122e71516f4f2cbd11907aa86d3ddd']
//            ],
//            '61cad324cdfe48b5be3b99e04e13a646' => [
//                'primaryKey' => '61cad324cdfe48b5be3b99e04e13a646',
//                'data' => ['id' => '61cad324cdfe48b5be3b99e04e13a646']
//            ],
            ];
            if ($result->getTotalPrimaryCount()) {
                throw new \Exception('There are no products from the Nosto.');
            }

            return new IdSearchResult($result->getTotalPrimaryCount(), $converted, $criteria,
                $salesChannelContext->getContext());
        } catch (\Exception $e) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }
    }
}
