<?php

declare(strict_types=1);

namespace Relwave\Model;

/**
 * The account that owns the API key.
 */
final class Me extends Model
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $accountSlug,
        public readonly string $accountName,
        public readonly bool $sandbox,
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
            accountSlug: (string) ($data['account']['slug'] ?? ''),
            accountName: (string) ($data['account']['name'] ?? ''),
            sandbox: (bool) ($data['sandbox'] ?? false),
            attributes: $data,
        );
    }
}
