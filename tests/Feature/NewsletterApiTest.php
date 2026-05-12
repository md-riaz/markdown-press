<?php

namespace Tests\Feature;

use App\Mail\NewsletterSubscriptionConfirmed;
use App\Models\ApiToken;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsletterApiTest extends TestCase
{
    use RefreshDatabase;

    private string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->rawToken = Str::random(64);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'newsletter-tests',
            'token' => hash('sha256', $this->rawToken),
            'abilities' => ['*'],
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_newsletter_subscribe_requires_authentication(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'reader@example.com',
        ])->assertStatus(401);
    }

    public function test_newsletter_subscribe_creates_active_subscriber(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'reader@example.com')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'status' => 'active',
        ]);

        Mail::assertSent(NewsletterSubscriptionConfirmed::class, function ($mail): bool {
            return $mail->hasTo('reader@example.com');
        });
    }

    public function test_newsletter_subscribe_reactivates_existing_subscriber(): void
    {
        Mail::fake();

        $subscriber = Subscriber::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'status' => 'unsubscribed',
            'token' => Str::random(64),
            'subscribed_at' => now()->subDay(),
            'unsubscribed_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'reader@example.com',
            'name' => 'Updated Reader',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.email', 'reader@example.com')
            ->assertJsonPath('data.status', 'active');

        $subscriber->refresh();

        $this->assertSame('Updated Reader', $subscriber->name);
        $this->assertSame('active', $subscriber->status);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertTrue($subscriber->subscribed_at->isAfter(now()->subMinute()));
        Mail::assertSent(NewsletterSubscriptionConfirmed::class, function ($mail): bool {
            return $mail->hasTo('reader@example.com');
        });
    }

    public function test_newsletter_subscribe_keeps_existing_subscription_date_for_active_subscriber(): void
    {
        Mail::fake();

        $subscribedAt = now()->subDays(7);

        Subscriber::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'status' => 'active',
            'token' => Str::random(64),
            'subscribed_at' => $subscribedAt,
        ]);

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.email', 'reader@example.com')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'reader@example.com',
            'status' => 'active',
        ]);
        $this->assertEquals(
            $subscribedAt->toDateTimeString(),
            Subscriber::where('email', 'reader@example.com')->value('subscribed_at')
        );
    }

    public function test_newsletter_unsubscribe_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'reader@example.com',
        ])->assertStatus(401);
    }

    public function test_newsletter_unsubscribe_marks_subscriber_as_unsubscribed(): void
    {
        $subscriber = Subscriber::create([
            'name' => 'Reader',
            'email' => 'reader@example.com',
            'status' => 'active',
            'token' => Str::random(64),
            'subscribed_at' => now()->subDay(),
        ]);

        $response = $this->deleteJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'reader@example.com',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.email', 'reader@example.com')
            ->assertJsonPath('data.status', 'unsubscribed');

        $subscriber->refresh();

        $this->assertSame('unsubscribed', $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->rawToken}"];
    }
}
