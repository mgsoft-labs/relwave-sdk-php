<?php

declare(strict_types=1);

namespace Relwave\Exception;

use Throwable;

/**
 * Thrown whenever the API returns an HTTP status >= 400 or a non-empty
 * `errors` envelope.
 *
 * The typed payload mirrors the Relwave `{ data, meta, errors }` contract:
 *
 *  - {@see ApiException::getStatusCode()}  HTTP status code (401, 403, 404, 422, 429, ...).
 *  - {@see ApiException::getErrors()}      Validation/runtime error map: `field => string[]`.
 *  - {@see ApiException::getMeta()}        Any `meta` returned alongside the error.
 *  - {@see ApiException::getRetryAfter()}  Seconds to wait, populated on 429 responses.
 */
class ApiException extends RelwaveException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $errors = [],
        private readonly array $meta = [],
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Build an exception from a decoded response envelope.
     *
     * Uses late static binding so subclasses such as
     * {@see RateLimitException} are instantiated for their own status codes.
     *
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(int $statusCode, array $envelope, ?int $retryAfter = null): static
    {
        $errors = self::normalizeErrors($envelope['errors'] ?? []);
        $meta = is_array($envelope['meta'] ?? null) ? $envelope['meta'] : [];

        return new static(
            message: self::buildMessage($statusCode, $errors),
            statusCode: $statusCode,
            errors: $errors,
            meta: $meta,
            retryAfter: $retryAfter ?? self::metaRetryAfter($meta),
        );
    }

    /**
     * The HTTP status code returned by the API.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The full error map keyed by field name.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convenience accessor for the first error message of a given field,
     * or the first message overall when no field is supplied.
     */
    public function firstError(?string $field = null): ?string
    {
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return null;
    }

    /**
     * The `meta` block that accompanied the error, when present.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Seconds to wait before retrying. Populated for 429 responses from the
     * `Retry-After` header (or the `retry_after_seconds` meta field).
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Whether this error represents a rate-limit (HTTP 429) response.
     */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * @param  mixed  $errors
     * @return array<string, array<int, string>>
     */
    private static function normalizeErrors($errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $key => $messages) {
            if (is_array($messages)) {
                $normalized[(string) $key] = array_values(array_map('strval', $messages));
            } else {
                $normalized[(string) $key] = [(string) $messages];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function metaRetryAfter(array $meta): ?int
    {
        return isset($meta['retry_after_seconds']) ? (int) $meta['retry_after_seconds'] : null;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function buildMessage(int $statusCode, array $errors): string
    {
        foreach ($errors as $field => $messages) {
            if ($messages !== []) {
                return sprintf('[%d] %s: %s', $statusCode, $field, $messages[0]);
            }
        }

        return sprintf('[%d] API request failed', $statusCode);
    }
}
