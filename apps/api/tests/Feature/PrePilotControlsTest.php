<?php

namespace Tests\Feature;

use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrePilotControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_correlation_returns_same_valid_id_and_preserves_body(): void
    {
        $response = $this->getJson('/api/health', ['X-Correlation-ID' => 'order-flow.123:abc_XYZ-9']);

        $response->assertOk()->assertJsonPath('status', 'healthy');
        $this->assertSame('order-flow.123:abc_XYZ-9', $response->headers->get('X-Correlation-ID'));
        $this->assertArrayHasKey('timestamp', $response->json());
    }

    public function test_correlation_generates_bounded_id_for_missing_or_unsafe_id(): void
    {
        $missing = $this->getJson('/api/health');
        $missing->assertOk()->assertJsonPath('status', 'healthy');
        $generated = (string) $missing->headers->get('X-Correlation-ID');
        $this->assertNotEmpty($generated);
        $this->assertLessThanOrEqual(100, strlen($generated));
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9._:\-]{1,100}\z/', $generated);

        $oversized = $this->getJson('/api/health', ['X-Correlation-ID' => str_repeat('a', 101)]);
        $oversized->assertOk();
        $regenerated = (string) $oversized->headers->get('X-Correlation-ID');
        $this->assertNotSame(str_repeat('a', 101), $regenerated);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9._:\-]{1,100}\z/', $regenerated);

        $unsafe = $this->getJson('/api/health', ['X-Correlation-ID' => 'bad id; DROP TABLE']);
        $unsafe->assertOk();
        $sanitized = (string) $unsafe->headers->get('X-Correlation-ID');
        $this->assertNotSame('bad id; DROP TABLE', $sanitized);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9._:\-]{1,100}\z/', $sanitized);
    }

    public function test_correlation_journal_records_one_redacted_event_on_success(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => false]);

        $response = $this->getJson(
            '/api/health?password=secret123&phone=%2B628123',
            ['X-Correlation-ID' => 'journal-success-1', 'Authorization' => 'BearerSECRET-should-not-persist']
        );
        $response->assertOk()->assertJsonPath('status', 'healthy');

        $events = OperationalEvent::where('correlation_id', 'journal-success-1')->get();
        $this->assertCount(1, $events, 'Exactly one operational event must be recorded per request.');
        $event = $events->first();
        $this->assertSame('success', $event->outcome);
        $this->assertSame(200, $event->status_code);
        $this->assertNull($event->actor_id);
        $this->assertNull($event->error_class);
        $this->assertNotNull($event->occurred_at);

        $raw = json_encode([$event->route, $event->action, $event->metadata]);
        $this->assertStringNotContainsString('secret123', $raw);
        $this->assertStringNotContainsString('BearerSECRET-should-not-persist', $raw);
        $this->assertStringNotContainsString('+628123', $raw);
        $this->assertStringNotContainsString('password', strtolower($raw));
    }

    public function test_correlation_journal_records_actor_and_failure_outcome(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => false]);

        $user = User::factory()->create([
            'role' => 'admin',
            'email' => 't3-admin@example.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $token = $this->postJson('/api/auth/login', [
            'email' => 't3-admin@example.test',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $correlationId = 'journal-actor-failure-1';
        $response = $this->withToken($token)->getJson('/api/auth/me', ['X-Correlation-ID' => $correlationId]);
        $response->assertOk()->assertJsonPath('data.id', $user->id);

        $actorEvent = OperationalEvent::where('correlation_id', $correlationId)->sole();
        $this->assertSame($user->id, $actorEvent->actor_id);
        $this->assertSame('success', $actorEvent->outcome);
        $this->assertStringNotContainsString($token, json_encode($actorEvent->metadata));

        $failed = $this->postJson('/api/auth/login', [], ['X-Correlation-ID' => 'journal-failure-1']);
        $failed->assertStatus(422)->assertJsonPath('status', 'error');

        $failure = OperationalEvent::where('correlation_id', 'journal-failure-1')->sole();
        $this->assertSame('failure', $failure->outcome);
        $this->assertSame(422, $failure->status_code);
        $this->assertNotNull($failure->error_class);
    }

    public function test_correlation_journal_failure_does_not_change_response(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => false]);
        Schema::drop('operational_events');

        $response = $this->getJson('/api/health', ['X-Correlation-ID' => 'journal-down-1']);

        $response->assertOk()->assertJsonPath('status', 'healthy');
        $this->assertSame('journal-down-1', $response->headers->get('X-Correlation-ID'));
    }

    public function test_flag_off_disables_journal_but_preserves_behavior(): void
    {
        config(['pre_pilot.enabled' => false, 'pre_pilot.kill_switch' => false]);

        $response = $this->getJson('/api/health', ['X-Correlation-ID' => 'flag-off-1']);

        $response->assertOk()->assertJsonPath('status', 'healthy');
        $this->assertSame('flag-off-1', $response->headers->get('X-Correlation-ID'));
        $this->assertSame(0, OperationalEvent::count());
    }

    public function test_flag_kill_switch_disables_journal(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => true]);

        $response = $this->getJson('/api/health', ['X-Correlation-ID' => 'flag-kill-1']);

        $response->assertOk()->assertJsonPath('status', 'healthy');
        $this->assertSame('flag-kill-1', $response->headers->get('X-Correlation-ID'));
        $this->assertSame(0, OperationalEvent::count());
    }
}
