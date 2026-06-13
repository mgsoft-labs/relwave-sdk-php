<?php

declare(strict_types=1);

namespace Avva\Newsletter\Resource;

use Avva\Newsletter\Http\Transport;
use Avva\Newsletter\Model\MailingList;

/**
 * Mailing list operations.
 */
final class Lists
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * List every mailing list in the account.
     *
     * @return array<int, MailingList>
     */
    public function list(): array
    {
        $envelope = $this->transport->request('GET', 'lists');

        return array_map(
            static fn (array $row): MailingList => MailingList::fromArray($row),
            is_array($envelope['data']) ? $envelope['data'] : [],
        );
    }
}
