<?php

declare(strict_types=1);

namespace Mgsoft\Flow\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mgsoft\Flow\Client;
use Mgsoft\Flow\Exception\ApiException;
use Mgsoft\Flow\Exception\RateLimitException;
use Mgsoft\Flow\Model\Message;
use Mgsoft\Flow\Model\Page;
use Mgsoft\Flow\Model\Subscriber;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var array<int, array{request: Request}> */
    private array $history = [];

    private MockHandler $mock;

    protected function setUp(): void
    {
        $this->history = [];
        $this->mock = new MockHandler;
    }

    public function test_it_sends_a_transactional_message(): void
    {
        $this->queue(201, [
            'data' => [
                'id' => 42,
                'message_id' => 'abc-123@app.test',
                'to' => 'jane@example.com',
                'subject' => 'Welcome',
                'status' => 'sent',
                'stream' => ['id' => 1, 'name' => 'Transactional', 'type' => 'transactional'],
                'sent_at' => '2026-06-13T10:00:00+00:00',
                'delivered_at' => null,
                'failure_reason' => null,
            ],
            'meta' => (object) [],
            'errors' => (object) [],
        ]);

        $client = $this->client();

        $message = $client->messages->send([
            'to' => 'jane@example.com',
            'subject' => 'Welcome',
            'html' => '<h1>Hello {{ name }}</h1>',
            'variables' => ['name' => 'Jane'],
        ], idempotencyKey: 'welcome-jane');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame(42, $message->id);
        $this->assertSame('sent', $message->status);
        $this->assertSame('Transactional', $message->stream?->name);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://app.test/api/v1/messages', (string) $request->getUri());
        $this->assertSame('Bearer nl_live_abc.secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('welcome-jane', $request->getHeaderLine('X-Idempotency-Key'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('jane@example.com', $body['to']);
        $this->assertSame(['name' => 'Jane'], $body['variables']);
    }

    public function test_it_creates_a_subscriber(): void
    {
        $this->queue(201, [
            'data' => [
                'id' => 7,
                'email' => 'new@example.com',
                'first_name' => 'New',
                'last_name' => null,
                'status' => 'confirmed',
                'language' => 'en',
                'engagement_tier' => 'active',
                'created_at' => '2026-06-13T10:00:00+00:00',
                'unsubscribed_at' => null,
            ],
            'meta' => (object) [],
            'errors' => (object) [],
        ]);

        $subscriber = $this->client()->subscribers->create([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'status' => 'confirmed',
        ]);

        $this->assertInstanceOf(Subscriber::class, $subscriber);
        $this->assertSame(7, $subscriber->id);
        $this->assertSame('new@example.com', $subscriber->email);
        $this->assertSame('confirmed', $subscriber->status);
        $this->assertSame('active', $subscriber->engagementTier);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://app.test/api/v1/subscribers', (string) $request->getUri());
    }

    public function test_it_lists_subscribers_with_pagination_meta(): void
    {
        $this->queue(200, [
            'data' => [
                ['id' => 1, 'email' => 'a@example.com', 'status' => 'confirmed'],
                ['id' => 2, 'email' => 'b@example.com', 'status' => 'pending'],
            ],
            'meta' => [
                'current_page' => 1,
                'last_page' => 3,
                'per_page' => 2,
                'total' => 5,
            ],
            'errors' => (object) [],
        ]);

        $page = $this->client()->subscribers->list(['per_page' => 2]);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertCount(2, $page);
        $this->assertSame(1, $page->currentPage);
        $this->assertSame(3, $page->lastPage);
        $this->assertSame(5, $page->total);
        $this->assertTrue($page->hasMorePages());
        $this->assertSame(2, $page->nextPage());
        $this->assertContainsOnlyInstancesOf(Subscriber::class, $page->items);

        $this->assertSame(
            'https://app.test/api/v1/subscribers?per_page=2',
            (string) $this->lastRequest()->getUri(),
        );
    }

    public function test_it_throws_typed_exception_on_validation_error(): void
    {
        $this->queue(422, [
            'data' => null,
            'meta' => (object) [],
            'errors' => [
                'to' => ['recipient is on the suppression list'],
                'subject' => ['The subject field is required.'],
            ],
        ]);

        try {
            $this->client()->messages->send([
                'to' => 'blocked@example.com',
                'subject' => '',
                'text' => 'hi',
            ]);
            $this->fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(
                ['recipient is on the suppression list'],
                $e->getErrors()['to'],
            );
            $this->assertSame('recipient is on the suppression list', $e->firstError('to'));
            $this->assertNull($e->getRetryAfter());
            $this->assertFalse($e->isRateLimited());
        }
    }

    public function test_it_throws_rate_limit_exception_with_retry_after(): void
    {
        $this->mock->append(new Response(
            429,
            ['Retry-After' => '17', 'Content-Type' => 'application/json'],
            (string) json_encode([
                'data' => null,
                'meta' => ['retry_after_seconds' => 17],
                'errors' => ['rate_limit' => ['60 req/1min limit exceeded']],
            ]),
        ));

        try {
            $this->client()->campaigns->list();
            $this->fail('Expected RateLimitException was not thrown.');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertTrue($e->isRateLimited());
            $this->assertSame(17, $e->getRetryAfter());
            $this->assertSame('60 req/1min limit exceeded', $e->firstError('rate_limit'));
        }
    }

    public function test_it_supports_the_x_api_key_auth_header_variant(): void
    {
        $this->queue(200, ['data' => [], 'meta' => (object) [], 'errors' => (object) []]);

        $this->client(['auth_header' => 'X-Api-Key'])->lists->list();

        $request = $this->lastRequest();
        $this->assertSame('nl_live_abc.secret', $request->getHeaderLine('X-Api-Key'));
        $this->assertSame('', $request->getHeaderLine('Authorization'));
    }

    public function test_it_detects_sandbox_keys(): void
    {
        $sandbox = new Client('nl_test_abc.secret', 'https://app.test/api/v1', $this->guzzle());
        $live = new Client('nl_live_abc.secret', 'https://app.test/api/v1', $this->guzzle());

        $this->assertTrue($sandbox->isSandbox());
        $this->assertFalse($live->isSandbox());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function queue(int $status, array $body): void
    {
        $this->mock->append(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode($body),
        ));
    }

    /**
     * @param  array{auth_header?: string}  $options
     */
    private function client(array $options = []): Client
    {
        return new Client('nl_live_abc.secret', 'https://app.test/api/v1', $this->guzzle(), $options);
    }

    private function guzzle(): GuzzleClient
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new GuzzleClient(['handler' => $stack, 'http_errors' => false]);
    }

    private function lastRequest(): Request
    {
        $last = end($this->history);
        $this->assertNotFalse($last, 'No request was recorded.');

        /** @var Request $request */
        $request = $last['request'];

        return $request;
    }
}
