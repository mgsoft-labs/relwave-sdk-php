<?php

declare(strict_types=1);

namespace Avva\Newsletter\Exception;

/**
 * Thrown specifically on HTTP 429 responses so callers can catch rate limits
 * separately from other API errors. {@see ApiException::getRetryAfter()}
 * carries the seconds to wait before retrying.
 */
class RateLimitException extends ApiException
{
}
