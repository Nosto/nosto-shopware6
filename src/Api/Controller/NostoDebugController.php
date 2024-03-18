<?php

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\NostoIntegration\Async\AbstractMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Model\Job\Message\JobMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoDebugController extends AbstractController
{
    public function __construct(
        private readonly ProductSyncHandler $productSyncHandler,
    ) {
    }

    #[Route(path: '/nosto-product-debug', name: 'storefront.nosto_integration.product_debug', methods: ['GET'])]
    public function debug(Request $request, SalesChannelContext $context): JsonResponse
    {
        $productId = $request->get('productId');
        $message = new ProductSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [
//                'c7bca22753c84d08b6178a50052b4146' => 'c7bca22753c84d08b6178a50052b4146',
//                '018dd17b848170958109d362dd7215d9' => '018dd17b848170958109d362dd7215d9',
//                '018dd17b848170958109d362dc1ba267' => '018dd17b848170958109d362dc1ba267',
//                '018dd17b848170958109d362dca7f104' => '018dd17b848170958109d362dca7f104',
//                '018dd17b848170958109d362dc8288fd' => '018dd17b848170958109d362dc8288fd',
//
//                '018dd17b848070ccb1af646ee016b405' => '018dd17b848070ccb1af646ee016b405',
//                '43a23e0c03bf4ceabc6055a2185faa87' => '43a23e0c03bf4ceabc6055a2185faa87',
//                '018dd17b848070ccb1af646ee165ff6f' => '018dd17b848070ccb1af646ee165ff6f',
//                '018dd17b848070ccb1af646ee2494ef4' => '018dd17b848070ccb1af646ee2494ef4',
//                '018dd17b848070ccb1af646ee01e21a3' => '018dd17b848070ccb1af646ee01e21a3',
//                '018dd17b848070ccb1af646ee0923fef' => '018dd17b848070ccb1af646ee0923fef',
//                '018dd17b848070ccb1af646ee20d6087' => '018dd17b848070ccb1af646ee20d6087',
//
//                '018dd58ee9ce720b8816e058e1464c87' => '018dd58ee9ce720b8816e058e1464c87',
//                '3ac014f329884b57a2cce5a29f34779c' => '3ac014f329884b57a2cce5a29f34779c',
//                '018defefccaf7e25bd8f864c830cc43a' => '018defefccaf7e25bd8f864c830cc43a',
//                '1901dc5e888f4b1ea4168c2c5f005540' => '1901dc5e888f4b1ea4168c2c5f005540',
//                '11dc680240b04f469ccba354cbf0b967' => '11dc680240b04f469ccba354cbf0b967',
//                '2a88d9b59d474c7e869d8071649be43c' => '2a88d9b59d474c7e869d8071649be43c',
//
                '018e425b700b77b2a57b37c1749873e0' => '018e425b700b77b2a57b37c1749873e0',
                '018e425c61807033bee530fed0c6fae0' => '018e425c61807033bee530fed0c6fae0',
                '018e425c61807033bee530fecffc4904' => '018e425c61807033bee530fecffc4904',
                '018e425c617f7248a3667c8749720e77' => '018e425c617f7248a3667c8749720e77',
//                '018e42a2ecdb734e81c0d0d338822bb5' => '018e42a2ecdb734e81c0d0d338822bb5',
            ],
            $context->getContext(),
            'Product Debug'
        );

        $result = $this->productSyncHandler->execute($message);

        return new JsonResponse([
            'messages' => array_map(static fn(JobMessage $message) => $message->getMessage(), $result->getMessages()),
            'errors' => array_map(static fn(JobMessage $message) => $message->getMessage(), $result->getErrors()),
        ]);
    }
}
