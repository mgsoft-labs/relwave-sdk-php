<?php

declare(strict_types=1);

namespace Avva\Newsletter\Http;

use Avva\Newsletter\Exception\ApiException;
use Avva\Newsletter\Exception\RateLimitException;
use Avva\Newsletter\Exception\TransportException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thin HTTP layer on top of a PSR-18 client (Guzzle by default).
 *
 * Responsibilities:
 *  - attach the `Authorization: Bearer` (or `X-Api-Key`) credential;
 *  - serialize JSON bodies and the optional `X-Idempotency-Key` header;
 *  - decode the `{ data, meta, errors }` envelope;
 *  - translate HTTP >= 400 / non-empty `errors` into typed exceptions,
 *    surfacing 429 as a {@see RateLimitException} carrying `retryAfter`.
 *
 * @internal Consumers should use {@see \Avva\Newsletter\Client} instead.
 */
final class Transport
{
    public const VERSION = '1.0.0';

    private ClientInterface $http;

    private string $baseUrl;

    /**
     * @param  array{auth_header?: string, timeout?: float, user_agent?: string}  $options
     */
    public function __construct(
        private readonly string $apiKey,
        string $baseUrl,
        ?ClientInterface $http = null,
        private readonly array $options = [],
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new GuzzleClient([
            'http_errors' => false,
            'timeout' => $options['timeout'] ?? 30.0,
        ]);
    }

    /**
     * Whether the configured API key targets the sandbox environment.
     */
    public function isSandbox(): bool
    {
        return str_starts_with($this->apiKey, 'nl_test_');
    }

    /**
     * Perform a request and return the decoded envelope as
     * `array{data: mixed, meta: array<string, mixed>}`.
     *
     * @param  array<string, mixed>|null  $query
     * @param  array<string, mixed>|null  $body
     * @return array{data: mixed, meta: array<string, mixed>}
     *
     * @throws ApiException
     * @throws RateLimitException
     * @throws TransportException
     */
    public function request(
        string $method,
        string $path,
        ?array $query = null,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): array {
        $response = $this->send($method, $path, $query, $body, $idempotencyKey);
        $status = $response->getStatusCode();
        $envelope = $this->decode($response);

        if ($status >= 400 || $this->hasErrors($envelope)) {
            throw $this->toException($status, $envelope, $response);
        }

        return [
            'data' => $envelope['data'] ?? null,
            'meta' => is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $query
     * @param  array<string, mixed>|null  $body
     */
    private function send(
        string $method,
        string $path,
        ?array $query,
        ?array $body,
        ?string $idempotencyKey,
    ): ResponseInterface {
        $uri = $this->baseUrl.'/'.ltrim($path, '/');

        if ($query !== null && $query !== []) {
            $uri .= '?'.http_build_query($query);
        }

        $headers = $this->headers($idempotencyKey);
        $payload = $body !== null ? $this->encode($body) : null;

        $request = new Request(strtoupper($method), $uri, $headers, $payload);

        try {
            return $this->http->sendRequest($request);
        } catch (ConnectException $e) {
            throw new TransportException('Could not reach the Newsletter API: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new TransportException('HTTP request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $idempotencyKey): array
    {
        $authHeader = $this->options['auth_header'] ?? 'Authorization';

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => $this->options['user_agent'] ?? ('avva-newsletter-php/'.self::VERSION),
        ];

        if (strtolower($authHeader) === 'x-api-key') {
            $headers['X-Api-Key'] = $this->apiKey;
        } else {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function encode(array $body): string
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();

        if ($raw === '') {
            return ['data' => null, 'meta' => [], 'errors' => []];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new TransportException(
                'Failed to decode API response as JSON: '.$e->getMessage(),
                $response->getStatusCode(),
                $e,
            );
        }

        if (! is_array($decoded)) {
            throw new TransportException('Unexpected API response shape: expected a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function hasErrors(array $envelope): bool
    {
        $errors = $envelope['errors'] ?? null;

        return is_array($errors) && $errors !== [];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function toException(int $status, array $envelope, ResponseInterface $response): ApiException
    {
        $retryAfter = $this->retryAfter($response);

        if ($status === 429) {
            return RateLimitException::fromEnvelope($status, $envelope, $retryAfter);
        }

        return ApiException::fromEnvelope($status, $envelope, $retryAfter);
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        return $header !== '' && is_numeric($header) ? (int) $header : null;
    }
}
