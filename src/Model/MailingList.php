<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Model;

/**
 * A mailing list (audience segment container).
 */
final class MailingList extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description = null,
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
            description: $data['description'] ?? null,
            createdAt: $data['created_at'] ?? null,
            attributes: $data,
        );
    }
}
