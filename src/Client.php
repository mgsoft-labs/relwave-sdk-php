<?php

declare(strict_types=1);

namespace Relwave;

use Psr\Http\Client\ClientInterface;
use Relwave\Http\Transport;
use Relwave\Resource\Campaigns;
use Relwave\Resource\Lists;
use Relwave\Resource\Messages;
use Relwave\Resource\Subscribers;

/**
 * Entry point for the Relwave /api/v1 SDK.
 *
 * ```php
 * $client = new \Relwave\Client('nl_live_xxxxx.secret');
 *
 * $message = $client->messages->send([
 *     'to'      => 'jane@example.com',
 *     'subject' => 'Welcome aboard',
 *     'html'    => '<h1>Hello {{ name }}</h1>',
 *     'variables' => ['name' => 'Jane'],
 * ], idempotencyKey: 'welcome-jane-2026-01');
 * ```
 *
 * Authentication uses `Authorization: Bearer <API_KEY>` by default. Pass
 * `['auth_header' => 'X-Api-Key']` in `$options` to use the header variant.
 *
 * Keys prefixed with `nl_test_` target the sandbox: messages are accepted and
 * echoed back without real delivery.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://app.test/api/v1';

    public readonly Subscribers $subscribers;

    public readonly Lists $lists;

    public readonly Campaigns $campaigns;

    public readonly Messages $messages;

    private readonly Transport $transport;

    /**
     * @param  string  $apiKey  API key in `nl_xxxxx.secret` form (`nl_test_` = sandbox).
     * @param  string  $baseUrl  Base URL including the `/api/v1` suffix.
     * @param  ClientInterface|null  $http  Optional PSR-18 client (defaults to Guzzle).
     * @param  array{auth_header?: 'Authorization'|'X-Api-Key', timeout?: float, user_agent?: string}  $options
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?ClientInterface $http = null,
        array $options = [],
    ) {
        $this->transport = new Transport($apiKey, $baseUrl, $http, $options);

        $this->subscribers = new Subscribers($this->transport);
        $this->lists = new Lists($this->transport);
        $this->campaigns = new Campaigns($this->transport);
        $this->messages = new Messages($this->transport);
    }

    /**
     * Whether this client is configured against the sandbox (key `nl_test_*`).
     */
    public function isSandbox(): bool
    {
        return $this->transport->isSandbox();
    }
}
