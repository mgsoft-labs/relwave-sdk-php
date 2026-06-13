<?php

declare(strict_types=1);

namespace Avva\Newsletter\Model;

/**
 * A transactional message.
 */
final class Message extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $messageId,
        public readonly string $status,
        public readonly ?string $to = null,
        public readonly ?string $subject = null,
        public readonly ?Stream $stream = null,
        public readonly ?string $sentAt = null,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $failureReason = null,
        array $attributes = [],
    ) {
        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $stream = isset($data['stream']) && is_array($data['stream'])
            ? Stream::fromArray($data['stream'])
            : null;

        return new self(
            id: (int) ($data['id'] ?? 0),
            messageId: (string) ($data['message_id'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            to: $data['to'] ?? null,
            subject: $data['subject'] ?? null,
            stream: $stream,
            sentAt: $data['sent_at'] ?? null,
            deliveredAt: $data['delivered_at'] ?? null,
            failureReason: $data['failure_reason'] ?? null,
            attributes: $data,
        );
    }

    /**
     * Whether the message was created in sandbox mode (no real delivery).
     */
    public function isSandbox(): bool
    {
        return (bool) ($this->attributes['sandbox'] ?? false);
    }
}
