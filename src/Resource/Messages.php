<?php

declare(strict_types=1);

namespace Relwave\Resource;

use Relwave\Http\Transport;
use Relwave\Model\Message;

/**
 * Transactional message operations.
 */
final class Messages
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * Send a single transactional message. At least one of `html` / `text`
     * is required alongside `to` and `subject`.
     *
     * @param  array{
     *     to: string,
     *     subject: string,
     *     html?: string,
     *     text?: string,
     *     from?: string,
     *     from_name?: string,
     *     reply_to?: string,
     *     stream_id?: int,
     *     variables?: array<string, scalar|null>,
     *     metadata?: array<string, mixed>,
     *     tags?: array<int, string>,
     *     markdown?: string,
     *     template_id?: int,
     *     attachments?: array<int, array{filename: string, content: string, content_type?: string}>
     * }  $body
     */
    public function send(array $body, ?string $idempotencyKey = null): Message
    {
        $envelope = $this->transport->request('POST', 'messages', body: $body, idempotencyKey: $idempotencyKey);

        return Message::fromArray((array) $envelope['data']);
    }

    /**
     * Retrieve a single message by id.
     */
    public function get(int $id): Message
    {
        $envelope = $this->transport->request('GET', 'messages/'.$id);

        return Message::fromArray((array) $envelope['data']);
    }
}
