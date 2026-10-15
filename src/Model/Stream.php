<?php

declare(strict_types=1);

namespace Relwave\Model;

/**
 * The message stream a transactional message was sent through.
 */
final class Stream extends Model
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $type = null,
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
            type: $data['type'] ?? null,
            attributes: $data,
        );
    }
}
