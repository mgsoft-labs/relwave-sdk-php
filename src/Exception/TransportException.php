<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Exception;

/**
 * Thrown when the request never produced an HTTP response (DNS failure,
 * connection refused, timeout) or the response body could not be decoded as
 * the expected JSON envelope.
 */
class TransportException extends FlowException {}
