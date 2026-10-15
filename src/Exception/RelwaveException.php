<?php

declare(strict_types=1);

namespace Relwave\Exception;

use Exception;

/**
 * Base type for every error thrown by the SDK.
 *
 * Catch this when you want to handle any SDK failure (network problems,
 * malformed responses, or API errors) with a single block.
 */
class RelwaveException extends Exception {}
