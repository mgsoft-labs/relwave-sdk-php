<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Resource;

use Mgsoft\Flow\Http\Transport;
use Mgsoft\Flow\Model\Page;
use Mgsoft\Flow\Model\Subscriber;

/**
 * Audience subscriber operations.
 */
final class Subscribers
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * List subscribers. Paginated.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<Subscriber>
     */
    public function list(array $params = []): Page
    {
        $envelope = $this->transport->request('GET', 'subscribers', query: $params);

        $items = array_map(
            static fn (array $row): Subscriber => Subscriber::fromArray($row),
            is_array($envelope['data']) ? $envelope['data'] : [],
        );

        return Page::fromMeta($items, $envelope['meta']);
    }

    /**
     * Create (or upsert) a subscriber. Idempotent on (account, email).
     *
     * @param  array{
     *     email: string,
     *     first_name?: string,
     *     last_name?: string,
     *     phone?: string,
     *     language?: string,
     *     status?: 'pending'|'confirmed'|'unsubscribed',
     *     attributes?: array<string, mixed>
     * }  $body
     */
    public function create(array $body, ?string $idempotencyKey = null): Subscriber
    {
        $envelope = $this->transport->request('POST', 'subscribers', body: $body, idempotencyKey: $idempotencyKey);

        return Subscriber::fromArray((array) $envelope['data']);
    }

    /**
     * Retrieve a single subscriber by id.
     */
    public function get(int $id): Subscriber
    {
        $envelope = $this->transport->request('GET', 'subscribers/'.$id);

        return Subscriber::fromArray((array) $envelope['data']);
    }

    /**
     * Soft-unsubscribe a subscriber. Returns the updated record.
     */
    public function delete(int $id): Subscriber
    {
        $envelope = $this->transport->request('DELETE', 'subscribers/'.$id);

        return Subscriber::fromArray((array) $envelope['data']);
    }
}
