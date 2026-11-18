<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ReportOverdueReminder;
use App\Notifications\ReportsAwaitingSignatureReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 02:00:00');
        $this->me = User::factory()->role(UserRole::Student)->create();
        $this->other = User::factory()->role(UserRole::Partner)->create();
    }

    private function notify(User $user, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $user->notify(new ReportOverdueReminder(1));
        }
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_list_is_own_only_newest_first_and_paginated_by_15(): void
    {
        $this->notify($this->me, 16);
        $this->travel(1)->hours();
        $this->me->notify(new ReportsAwaitingSignatureReminder(2));
        $this->notify($this->other, 3);

        $response = $this->actingAs($this->me)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)
            ->assertJsonStructure(['data' => [['id', 'category', 'message', 'action_path', 'action_label', 'read', 'created_at', 'created_human']]]);

        $this->assertStringContainsString('2 weekly reports', $response->json('data.0.message'));
        $this->assertSame('just now', $response->json('data.0.created_human'));
        $this->assertSame('1 h ago', $response->json('data.1.created_human'));
        $this->assertFalse($response->json('data.0.read'));

        $this->actingAs($this->me)->getJson('/api/notifications?page=2')->assertJsonCount(2, 'data');
        $this->actingAs($this->me)->getJson('/api/notifications?filter=bogus')->assertUnprocessable();
    }

    public function test_unread_count_mark_read_and_unread_filter(): void
    {
        $this->notify($this->me, 3);
        $id = $this->me->notifications()->first()->id;

        $this->actingAs($this->me)->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 3);

        $this->actingAs($this->me)->postJson("/api/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);

        $this->actingAs($this->me)->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 2);
        $this->actingAs($this->me)->getJson('/api/notifications?filter=unread')->assertJsonCount(2, 'data');
        $this->actingAs($this->me)->getJson('/api/notifications?filter=all')->assertJsonCount(3, 'data');
    }

    public function test_marking_someone_elses_notification_is_404(): void
    {
        $this->notify($this->other);
        $id = $this->other->notifications()->first()->id;

        $this->actingAs($this->me)->postJson("/api/notifications/{$id}/read")->assertNotFound();
        $this->assertNull($this->other->notifications()->first()->read_at);
        $this->actingAs($this->me)->postJson('/api/notifications/not-a-uuid/read')->assertNotFound();
    }

    public function test_read_all_only_touches_own(): void
    {
        $this->notify($this->me, 4);
        $this->notify($this->other, 2);

        $this->actingAs($this->me)->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 4);

        $this->assertSame(0, $this->me->unreadNotifications()->count());
        $this->assertSame(2, $this->other->unreadNotifications()->count());
    }

    public function test_preferences_read_and_update(): void
    {
        $this->actingAs($this->me)->getJson('/api/me/notification-preferences')
            ->assertOk()->assertExactJson(['data' => ['email_notifications' => true]]);

        $this->actingAs($this->me)->putJson('/api/me/notification-preferences', ['email_notifications' => false])
            ->assertOk()->assertJsonPath('data.email_notifications', false);
        $this->assertFalse($this->me->fresh()->email_notifications);

        $this->actingAs($this->me)->putJson('/api/me/notification-preferences', [])->assertUnprocessable();
        $this->actingAs($this->me)->putJson('/api/me/notification-preferences', ['email_notifications' => 'maybe'])->assertUnprocessable();
    }

    public function test_every_role_can_use_the_api(): void
    {
        foreach ([UserRole::Admin, UserRole::Coordinator, UserRole::Partner, UserRole::Student] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs(User::factory()->role($role)->create())->getJson('/api/notifications/unread-count')->assertOk();
        }
    }
}
