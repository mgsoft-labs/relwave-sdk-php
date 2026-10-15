<?php

declare(strict_types=1);

namespace Relwave\Model;

/**
 * Lightweight DTO base. Each model is hydrated from the decoded `data` payload
 * and keeps the raw attribute array around so forward-compatible fields stay
 * accessible even when they are not yet promoted to typed properties.
 */
abstract class Model
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public readonly array $attributes = []) {}

    /**
     * Hydrate the model from a decoded `data` array.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Access a raw attribute by key, falling back to a default.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * The raw attribute array as returned by the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
