<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Resource;

use Mgsoft\Flow\Http\Transport;
use Mgsoft\Flow\Model\Campaign;

/**
 * Campaign operations.
 */
final class Campaigns
{
    public function __construct(private readonly Transport $transport) {}

    /**
     * List every campaign in the account.
     *
     * @return array<int, Campaign>
     */
    public function list(): array
    {
        $envelope = $this->transport->request('GET', 'campaigns');

        return array_map(
            static fn (array $row): Campaign => Campaign::fromArray($row),
            is_array($envelope['data']) ? $envelope['data'] : [],
        );
    }
}
