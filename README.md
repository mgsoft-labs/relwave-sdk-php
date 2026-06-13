# MGSoft Flow PHP SDK

Official PHP client for the **MGSoft Flow** `/api/v1` transactional and audience
API. Typed models, a single response envelope (`{ data, meta, errors }`), typed
exceptions, idempotency, and rate-limit handling out of the box.

- **PHP** 8.1+
- **HTTP** [Guzzle](https://docs.guzzlephp.org/) (any PSR-18 client works)

## Installation

> **Yakında.** Paket henüz Packagist'e yayınlanmadı; aşağıdaki komut yayın sonrası geçerli olacak. O zamana kadar repoyu bir `path`/`vcs` repository olarak ekleyerek kullanabilirsiniz.

```bash
composer require mgsoft/flow-php
```

## Quick start

Construct a client with your API key. Keys look like `nl_xxxxx.secret`; keys
prefixed with `nl_test_` target the **sandbox** (accepted and echoed back
without real delivery).

```php
use Mgsoft\Flow\Client;

$client = new Client(
    apiKey: 'nl_live_abcde.secret',
    baseUrl: 'https://your-app.com/api/v1', // defaults to https://app.test/api/v1
);
```

### Send a transactional message

At least one of `html` / `text` is required, alongside `to` and `subject`.

```php
$message = $client->messages->send([
    'to'        => 'jane@example.com',
    'subject'   => 'Welcome aboard',
    'html'      => '<h1>Hello {{ name }}</h1>',
    'variables' => ['name' => 'Jane'],
    'tags'      => ['onboarding'], // up to 10
]);

echo $message->id;        // 42
echo $message->messageId; // abc-123@your-app.com
echo $message->status;    // "sent"
echo $message->stream?->name;
```

Retrieve it later:

```php
$message = $client->messages->get(42);
```

### Create a subscriber

`create()` upserts on (account, email). Status is one of
`pending`, `confirmed`, `unsubscribed`.

```php
$subscriber = $client->subscribers->create([
    'email'      => 'jane@example.com',
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
    'language'   => 'en',
    'status'     => 'confirmed',
    'attributes' => ['plan' => 'pro'],
]);

echo $subscriber->id;             // 7
echo $subscriber->engagementTier; // "active"
```

Read, and soft-unsubscribe:

```php
$subscriber = $client->subscribers->get(7);
$client->subscribers->delete(7); // soft unsubscribe — returns the updated record
```

### Lists & campaigns

```php
foreach ($client->lists->list() as $list) {
    echo $list->name;
}

foreach ($client->campaigns->list() as $campaign) {
    echo $campaign->name . ' — ' . $campaign->status;
}
```

## Pagination

List endpoints that paginate (currently `subscribers.list`) return a
`Page` object. It is iterable and carries the `meta` pagination fields.

```php
$page = $client->subscribers->list(['per_page' => 50]);

foreach ($page as $subscriber) {
    echo $subscriber->email;
}

$page->currentPage;    // 1
$page->lastPage;       // 4
$page->perPage;        // 50
$page->total;          // 187
$page->hasMorePages(); // true
$page->nextPage();     // 2

// Walk every page:
$next = 1;
do {
    $page = $client->subscribers->list(['page' => $next, 'per_page' => 100]);
    // ... process $page->items ...
    $next = $page->nextPage();
} while ($next !== null);
```

## Error handling

Any HTTP status `>= 400` — or a non-empty `errors` envelope — throws a typed
`ApiException` carrying the status code and the `field => string[]` error map.

```php
use Mgsoft\Flow\Exception\ApiException;
use Mgsoft\Flow\Exception\RateLimitException;

try {
    $client->messages->send([
        'to'      => 'blocked@example.com',
        'subject' => 'Hi',
        'text'    => 'Hello',
    ]);
} catch (RateLimitException $e) {
    // 429 — back off and retry.
    sleep($e->getRetryAfter() ?? 1);
} catch (ApiException $e) {
    $e->getStatusCode();          // 401 | 403 | 404 | 422 | 429
    $e->getErrors();              // ['to' => ['recipient is on the suppression list']]
    $e->firstError('to');         // 'recipient is on the suppression list'
    $e->firstError();             // first message of any field
    $e->getMeta();                // any meta returned with the error
}
```

Exception hierarchy:

| Exception | Thrown when |
| --- | --- |
| `Mgsoft\Flow\Exception\FlowException` | Base type for every SDK error. |
| `Mgsoft\Flow\Exception\ApiException` | HTTP `>= 400` or non-empty `errors`. Carries status, errors, meta, retryAfter. |
| `Mgsoft\Flow\Exception\RateLimitException` | HTTP `429` (subclass of `ApiException`). `getRetryAfter()` is populated. |
| `Mgsoft\Flow\Exception\TransportException` | Network failure or undecodable response (no HTTP envelope). |

`RateLimitException` extends `ApiException`, so a single `catch (ApiException)`
handles everything; catch `RateLimitException` first when you want bespoke
back-off logic.

## Idempotency

`POST` methods accept an optional idempotency key, sent as the
`X-Idempotency-Key` header. Repeating a request with the same key replays the
original response instead of performing the work twice (24h window).

```php
$client->messages->send($payload, idempotencyKey: 'order-1042-receipt');
$client->subscribers->create($payload, idempotencyKey: 'import-batch-7:jane');
```

## Sandbox

Use an `nl_test_*` key to exercise the API without sending real email.
Messages are accepted and echoed back marked as sandbox.

```php
$client = new Client('nl_test_abcde.secret', 'https://your-app.com/api/v1');

$client->isSandbox(); // true

$message = $client->messages->send([
    'to'      => 'sink@example.com',
    'subject' => 'Sandbox check',
    'text'    => 'No real delivery.',
]);

$message->isSandbox(); // true
```

## Authentication header

By default the SDK sends `Authorization: Bearer <API_KEY>`. To use the
`X-Api-Key` variant instead:

```php
$client = new Client(
    apiKey: 'nl_live_abcde.secret',
    baseUrl: 'https://your-app.com/api/v1',
    options: ['auth_header' => 'X-Api-Key'],
);
```

Other options: `timeout` (float, seconds) and `user_agent` (string).

## Custom HTTP client

Any PSR-18 client may be injected as the third constructor argument — useful for
tests (Guzzle's `MockHandler`) or shared middleware:

```php
use GuzzleHttp\Client as GuzzleClient;

$client = new Client('nl_live_abcde.secret', 'https://your-app.com/api/v1', new GuzzleClient([
    'timeout' => 10,
]));
```

## API reference

| Method | HTTP | Returns |
| --- | --- | --- |
| `subscribers->list($params = [])` | `GET /subscribers` | `Page<Subscriber>` |
| `subscribers->create($body, $idempotencyKey = null)` | `POST /subscribers` | `Subscriber` |
| `subscribers->get($id)` | `GET /subscribers/{id}` | `Subscriber` |
| `subscribers->delete($id)` | `DELETE /subscribers/{id}` | `Subscriber` (unsubscribed) |
| `lists->list()` | `GET /lists` | `MailingList[]` |
| `campaigns->list()` | `GET /campaigns` | `Campaign[]` |
| `messages->send($body, $idempotencyKey = null)` | `POST /messages` | `Message` |
| `messages->get($id)` | `GET /messages/{id}` | `Message` |

## Testing

```bash
composer install
composer test
```

The test suite uses Guzzle's `MockHandler` — no network access required.

## License

MIT. See [LICENSE](LICENSE).
