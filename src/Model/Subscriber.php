<?php

declare(strict_types=1);

namespace Relwave\Model;

/**
 * A subscriber in an account's audience.
 */
final class Subscriber extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $status,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $language = null,
        public readonly ?string $engagementTier = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $unsubscribedAt = null,
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
            email: (string) ($data['email'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            language: $data['language'] ?? null,
            engagementTier: $data['engagement_tier'] ?? null,
            createdAt: $data['created_at'] ?? null,
            unsubscribedAt: $data['unsubscribed_at'] ?? null,
            attributes: $data,
        );
    }
}
