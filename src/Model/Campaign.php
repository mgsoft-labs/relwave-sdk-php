<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Model;

/**
 * A broadcast campaign.
 */
final class Campaign extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $status,
        public readonly ?string $subject = null,
        public readonly ?string $scheduledAt = null,
        public readonly ?string $sentAt = null,
        public readonly ?string $createdAt = null,
        array $attributes = [],
    ) {
        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            subject: $data['subject'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
            sentAt: $data['sent_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            attributes: $data,
        );
    }
}
