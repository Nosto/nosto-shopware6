<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Nosto\NostoIntegration\Search\Request\SessionParamsProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionParamsProviderTest extends TestCase
{
    private const ACCOUNT_ID = 'ji4s26yf';

    private const CLIENT_ID = '6ab64980076b6d4a696fdcd1';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $history = [];

    public function testReturnsNullWithoutNostoConsent(): void
    {
        $provider = $this->createProvider([]);
        $request = $this->createRequest([
            '2c_cId' => self::CLIENT_ID,
        ]);

        self::assertNull($provider->getSessionParams($request, self::ACCOUNT_ID, false));
        self::assertCount(0, $this->history);
    }

    public function testClearsCachedParamsWhenConsentIsWithdrawn(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $session = new Session(new MockArraySessionStorage());

        self::assertNotNull($provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false));
        self::assertTrue($session->has('nosto_search_session_params'));

        $withoutConsent = $this->createRequest([
            '2c_cId' => self::CLIENT_ID,
        ], $session);

        self::assertNull($provider->getSessionParams($withoutConsent, self::ACCOUNT_ID, false));
        self::assertFalse($session->has('nosto_search_session_params'));
        self::assertCount(1, $this->history);
    }

    public function testReturnsNullWithoutNostoVisitor(): void
    {
        $provider = $this->createProvider([]);
        $request = $this->createRequest([
            'nosto-integration-allowed' => '1',
        ]);

        self::assertNull($provider->getSessionParams($request, self::ACCOUNT_ID, false));
        self::assertCount(0, $this->history);
    }

    public function testBuildsSessionParamsFromAllAffinities(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $request = $this->createRequest();

        $params = $provider->getSessionParams($request, self::ACCOUNT_ID, false);

        self::assertSame([
            'segments' => ['613aa0000000000000000002', '61c26a800000000000000002'],
            'products' => [
                'personalizationBoost' => [
                    [
                        'field' => 'affinities.categories',
                        'value' => ['/sale'],
                        'weight' => 1.2,
                    ],
                    [
                        'field' => 'affinities.categories',
                        'value' => ['/matratzen'],
                        'weight' => 0.9,
                    ],
                    [
                        'field' => 'affinities.brand',
                        'value' => ['mlily'],
                        'weight' => 0.2857142857142857,
                    ],
                    [
                        'field' => 'affinities.brand',
                        'value' => ['dreambiance', 'sleepsy'],
                        'weight' => 0.14285714285714285,
                    ],
                    [
                        'field' => 'affinities.productType',
                        'value' => ['komfortschaumtopper'],
                        'weight' => 0.2857142857142857,
                    ],
                ],
            ],
        ], $params);
    }

    public function testDoesNotForwardShopperCookiesAndUsesVisitorId(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);

        $provider->getSessionParams($this->createRequest(), self::ACCOUNT_ID, false);

        /** @var Psr7Request $sentRequest */
        $sentRequest = $this->history[0]['request'];
        parse_str($sentRequest->getUri()->getQuery(), $query);

        self::assertSame('connect.nosto.com', $sentRequest->getUri()->getHost());
        self::assertSame(self::CLIENT_ID, $query['c']);
        self::assertSame(self::ACCOUNT_ID, $query['m']);
        self::assertSame('true', $query['skipEvents']);
        self::assertSame('true', $query['skipPageViews']);
        self::assertFalse($sentRequest->hasHeader('X-Nosto-Optout'));
        self::assertFalse($sentRequest->hasHeader('Cookie'));
    }

    public function testCachesSessionParamsInSession(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $session = new Session(new MockArraySessionStorage());

        $first = $provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false);
        $second = $provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false);

        self::assertNotNull($first);
        self::assertSame($first, $second);
        self::assertCount(1, $this->history);
    }

    public function testFetchesOncePerRequestWithoutSession(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $request = $this->createRequest(null, null);

        $provider->getSessionParams($request, self::ACCOUNT_ID, false);
        $provider->getSessionParams($request, self::ACCOUNT_ID, false);

        self::assertCount(1, $this->history);
    }

    public function testRefetchesWhenVisitorChanges(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], (string) json_encode($this->ev1Response())),
            new Response(200, [], (string) json_encode($this->ev1Response())),
        ]);
        $session = new Session(new MockArraySessionStorage());

        $provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false);
        $provider->getSessionParams(
            $this->createRequest([
                'nosto-integration-allowed' => '1',
                '2c_cId' => 'another-visitor',
            ], $session),
            self::ACCOUNT_ID,
            false,
        );

        self::assertCount(2, $this->history);
    }

    public function testFailureReturnsNullAndIsRememberedBriefly(): void
    {
        $provider = $this->createProvider([
            new ConnectException('timeout', new Psr7Request('GET', 'https://connect.nosto.com/ev1')),
        ]);
        $session = new Session(new MockArraySessionStorage());

        self::assertNull($provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false));
        self::assertNull($provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false));
        self::assertCount(1, $this->history);
    }

    public function testReturnsNullAndCachesWhenNostoResponseHasUnexpectedShape(): void
    {
        $provider = $this->createProvider([
            new Response(
                200,
                [],
                '{"se":{"active_segments":"broken"},"af":{"top_brands":[{"name":"mlily","score":[1]}]}}',
            ),
        ]);
        $session = new Session(new MockArraySessionStorage());

        self::assertNull($provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false));
        self::assertNull($provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false));
        self::assertCount(1, $this->history);
    }

    public function testReturnsNullWhenNostoHasNoData(): void
    {
        $provider = $this->createProvider([new Response(200, [], '{"af":{},"se":{"active_segments":[]}}')]);

        self::assertNull($provider->getSessionParams($this->createRequest(), self::ACCOUNT_ID, false));
    }

    public function testReturnsNullWhenConsentCookieIsEmpty(): void
    {
        $provider = $this->createProvider([]);
        $request = $this->createRequest([
            'nosto-integration-allowed' => '',
            '2c_cId' => self::CLIENT_ID,
        ]);

        self::assertNull($provider->getSessionParams($request, self::ACCOUNT_ID, false));
        self::assertCount(0, $this->history);
    }

    public function testSendsOptOutHeaderWhenShopperIsNotTracked(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);

        $provider->getSessionParams($this->createRequest(), self::ACCOUNT_ID, true);

        /** @var Psr7Request $sentRequest */
        $sentRequest = $this->history[0]['request'];
        self::assertSame('1', $sentRequest->getHeaderLine('X-Nosto-Optout'));
    }

    public function testLeavesOutMissingHeaders(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $request = $this->createRequest();
        $request->headers->remove('User-Agent');
        $request->headers->remove('Accept-Language');

        self::assertNotNull($provider->getSessionParams($request, self::ACCOUNT_ID, false));

        /** @var Psr7Request $sentRequest */
        $sentRequest = $this->history[0]['request'];
        self::assertSame('application/json', $sentRequest->getHeaderLine('Accept'));
        self::assertFalse($sentRequest->hasHeader('Accept-Language'));
    }

    public function testRefetchesWhenAccountOrTrackingStateChanges(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], (string) json_encode($this->ev1Response())),
            new Response(200, [], (string) json_encode($this->ev1Response())),
            new Response(200, [], (string) json_encode($this->ev1Response())),
        ]);
        $session = new Session(new MockArraySessionStorage());

        $provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false);
        $provider->getSessionParams($this->createRequest(null, $session), 'other-account', false);
        $provider->getSessionParams($this->createRequest(null, $session), 'other-account', true);

        self::assertCount(3, $this->history);
    }

    public function testIgnoresInvalidCachedValue(): void
    {
        $provider = $this->createProvider([new Response(200, [], (string) json_encode($this->ev1Response()))]);
        $session = new Session(new MockArraySessionStorage());
        $session->set('nosto_search_session_params', [
            'key' => self::ACCOUNT_ID . '|' . self::CLIENT_ID . '|track',
            'params' => 'broken',
            'expiresAt' => time() + 60,
        ]);

        $params = $provider->getSessionParams($this->createRequest(null, $session), self::ACCOUNT_ID, false);

        self::assertIsArray($params);
        self::assertCount(1, $this->history);
    }

    /**
     * @param array<int, mixed> $responses
     */
    private function createProvider(array $responses): SessionParamsProvider
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new SessionParamsProvider(new NullLogger(), new Client([
            'handler' => $stack,
        ]));
    }

    /**
     * @param array<string, string>|null $cookies
     */
    private function createRequest(
        ?array $cookies = null,
        ?Session $session = new Session(new MockArraySessionStorage()),
    ): Request {
        $request = Request::create('https://shop.test/search?search=topper', 'GET', [], $cookies ?? [
            'nosto-integration-allowed' => '1',
            '2c_cId' => self::CLIENT_ID,
        ]);

        if ($session) {
            $request->setSession($session);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function ev1Response(): array
    {
        return [
            'af' => [
                'top_categories' => [
                    [
                        'name' => '/sale',
                        'score' => 1.2,
                    ],
                    [
                        'name' => '/matratzen',
                        'score' => 0.9,
                    ],
                ],
                'top_brands' => [
                    [
                        'name' => 'mlily',
                        'score' => 0.2857142857142857,
                    ],
                    [
                        'name' => 'dreambiance',
                        'score' => 0.14285714285714285,
                    ],
                    [
                        'name' => 'sleepsy',
                        'score' => 0.14285714285714285,
                    ],
                ],
                'top_product_types' => [
                    [
                        'name' => 'komfortschaumtopper',
                        'score' => 0.2857142857142857,
                    ],
                ],
                'top_skus' => [],
            ],
            'se' => [
                'active_segments' => [
                    [
                        'id' => '613aa0000000000000000002',
                    ],
                    [
                        'id' => '61c26a800000000000000002',
                    ],
                ],
            ],
        ];
    }
}
