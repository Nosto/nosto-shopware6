<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Resolves the shopper's search session params (segments and affinities) on the server.
 *
 * They used to be copied from the browser into a cookie, which made requests fail with
 * "400 Request Header Or Cookie Too Large" on shops with many cookies (NS-14701).
 * Now they are fetched from Nosto and kept in the Shopware session for a short time.
 */
class SessionParamsProvider
{
    private const EV1_URL = 'https://connect.nosto.com/ev1';

    // PHP turns the dot of Nosto's "2c.cId" cookie name into an underscore
    private const NOSTO_CLIENT_ID_COOKIE = '2c_cId';

    private const SESSION_KEY = 'nosto_search_session_params';

    private const REQUEST_ATTRIBUTE = 'nostoSearchSessionParams';

    // Short, so affinities from the last pages viewed reach search quickly. Failed calls are kept
    // for the same time, so an unavailable Nosto doesn't slow down every search.
    private const CACHE_TTL_SECONDS = 60;

    private const TIMEOUT_SECONDS = 2;

    private const CONNECT_TIMEOUT_SECONDS = 1;

    private const AFFINITY_FIELDS = [
        'top_categories' => 'affinities.categories',
        'top_brands' => 'affinities.brand',
        'top_product_types' => 'affinities.productType',
    ];

    private readonly ClientInterface $client;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?ClientInterface $client = null,
    ) {
        $this->client = $client ?? new Client([
            'timeout' => self::TIMEOUT_SECONDS,
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSessionParams(Request $request, string $nostoAccountId, bool $doNotTrack): ?array
    {
        $clientId = (string) $request->cookies->get(self::NOSTO_CLIENT_ID_COOKIE);

        // Same rule as the storefront script: no consent means Nosto is not loaded and no visitor exists
        if (!$nostoAccountId || !$clientId || !$this->hasNostoConsent($request)) {
            return null;
        }

        // Search can be sent twice per request (filters + results), so only fetch once
        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            return $request->attributes->get(self::REQUEST_ATTRIBUTE);
        }

        // Anything that changes Nosto's answer is part of the key, so cached params are never reused for it
        $cacheKey = implode('|', [$nostoAccountId, $clientId, $doNotTrack ? 'optout' : 'track']);

        $session = $this->getSession($request);
        $cached = $session?->get(self::SESSION_KEY);
        if (is_array($cached)
            && ($cached['key'] ?? null) === $cacheKey
            && ($cached['expiresAt'] ?? 0) > time()
            && (is_array($cached['params'] ?? null) || ($cached['params'] ?? null) === null)
        ) {
            $params = $cached['params'] ?? null;
        } else {
            $params = $this->fetchFromNosto($request, $nostoAccountId, $clientId, $doNotTrack);
            $session?->set(self::SESSION_KEY, [
                'key' => $cacheKey,
                'params' => $params,
                'expiresAt' => time() + self::CACHE_TTL_SECONDS,
            ]);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $params);

        return $params;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromNosto(
        Request $request,
        string $nostoAccountId,
        string $clientId,
        bool $doNotTrack,
    ): ?array {
        $isSearch = str_contains($request->getPathInfo(), '/search');
        $isCategory = $request->attributes->has('navigationId');

        $message = [
            'url' => $request->getUri(),
            'response_mode' => 'HTML',
            'referrer' => $request->headers->get('referer'),
            'page_type' => $isSearch ? 'search' : ($isCategory ? 'category' : 'other'),
            'elements' => [],
            'cart' => [],
            'events' => [],
        ];

        $headers = array_filter([
            'User-Agent' => $request->headers->get('User-Agent'),
            'Accept' => 'application/json',
            'Accept-Language' => $request->headers->get('Accept-Language'),
            // Sent by the Nosto storefront script too, when the shopper did not accept tracking
            'X-Nosto-Optout' => $doNotTrack ? '1' : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        try {
            $response = $this->client->request('GET', self::EV1_URL, [
                'query' => [
                    'c' => $clientId,
                    'm' => $nostoAccountId,
                    'message' => json_encode($message),
                    // Only read the shopper's data, without counting a page view or event in Nosto analytics
                    'skipPageViews' => 'true',
                    'skipEvents' => 'true',
                ],
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            $this->logger->warning('Nosto search session params could not be fetched: ' . $e->getMessage());

            return null;
        }

        return is_array($responseData) ? $this->buildSessionParams($responseData) : null;
    }

    /**
     * Builds the same structure the Nosto storefront script returns from getSearchSessionParams().
     *
     * @param array<string, mixed> $responseData
     * @return array<string, mixed>|null
     */
    private function buildSessionParams(array $responseData): ?array
    {
        $segments = array_values(array_filter(
            array_column($responseData['se']['active_segments'] ?? [], 'id'),
        ));

        $personalizationBoost = [];
        foreach (self::AFFINITY_FIELDS as $affinityKey => $field) {
            $affinities = $responseData['af'][$affinityKey] ?? [];
            if (!is_array($affinities)) {
                continue;
            }

            // Affinities with the same score are grouped into one entry, like the storefront script does
            $entries = [];
            foreach ($affinities as $affinity) {
                if (!isset($affinity['name'], $affinity['score'])) {
                    continue;
                }

                $scoreKey = sprintf('%.17g', $affinity['score']);
                $entries[$scoreKey] ??= [
                    'field' => $field,
                    'value' => [],
                    'weight' => $affinity['score'],
                ];
                $entries[$scoreKey]['value'][] = $affinity['name'];
            }

            array_push($personalizationBoost, ...array_values($entries));
        }

        if (!$segments && !$personalizationBoost) {
            return null;
        }

        return [
            'segments' => $segments,
            'products' => [
                'personalizationBoost' => $personalizationBoost,
            ],
        ];
    }

    private function hasNostoConsent(Request $request): bool
    {
        return (bool) $request->cookies->get(NostoCookieProvider::NOSTO_COOKIE_KEY)
            || (bool) $request->cookies->get(NostoCookieProvider::LEGACY_TRACK_ALLOW_COOKIE_KEY);
    }

    private function getSession(Request $request): ?SessionInterface
    {
        try {
            return $request->hasSession() ? $request->getSession() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
