<?php

namespace Tests\Feature;

use App\Enums\PartnerDocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\IndustryPartner;
use App\Models\PartnerDocument;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReturned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Program $bsit;

    private Program $bsab;

    private User $bsitCoordinator;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->bsab = Program::create(['code' => 'BSAB', 'name' => 'BS Agribusiness']);
        $this->bsitCoordinator = User::factory()->role(UserRole::Coordinator)->create(['program_id' => $this->bsit->id]);
    }

    private function student(Program $program, UserStatus $status = UserStatus::Pending, array $user = []): User
    {
        return Student::factory()
            ->for(User::factory()->role(UserRole::Student)->status($status)->state(['program_id' => $program->id] + $user))
            ->create()
            ->user;
    }

    private function partner(UserStatus $status = UserStatus::Pending, array $profile = []): User
    {
        return IndustryPartner::factory()
            ->for(User::factory()->role(UserRole::Partner)->status($status))
            ->create($profile)
            ->user;
    }

    private const RETURN_REASON = 'Your student number does not match the registrar records.';

    // Scope

    public function test_a_coordinator_sees_only_their_programs_students(): void
    {
        $mine = $this->student($this->bsit, user: ['name' => 'Ana BSIT']);
        $this->student($this->bsab, user: ['name' => 'Ben BSAB']);

        $this->actingAs($this->bsitCoordinator, 'web')
            ->getJson('/api/approvals/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.program.code', 'BSIT')
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'email', 'status', 'student_number', 'year_level', 'program', 'contact_number',
                'address', 'city', 'province', 'latitude', 'longitude', 'geocode_status', 'created_at',
                'reviewed_at', 'review_note',
            ]], 'meta' => ['total', 'per_page']])
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_a_coordinator_cannot_act_on_another_programs_student(): void
    {
        $other = $this->student($this->bsab);

        $this->actingAs($this->bsitCoordinator, 'web');
        $this->postJson("/api/approvals/students/{$other->id}/approve")->assertForbidden();
        $this->postJson("/api/approvals/students/{$other->id}/return", ['reason' => self::RETURN_REASON])->assertForbidden();

        $this->assertSame(UserStatus::Pending, $other->fresh()->status);
    }

    public function test_a_coordinator_cannot_reach_partner_approvals(): void
    {
        $partner = $this->partner();

        $this->actingAs($this->bsitCoordinator, 'web');
        $this->getJson('/api/approvals/partners')->assertForbidden();
        $this->postJson("/api/approvals/partners/{$partner->id}/approve")->assertForbidden();
        $this->postJson("/api/approvals/partners/{$partner->id}/return", ['reason' => self::RETURN_REASON])->assertForbidden();
    }

    public function test_students_and_partners_cannot_reach_approvals(): void
    {
        $this->actingAs($this->student($this->bsit, UserStatus::Active), 'web');
        $this->getJson('/api/approvals/counts')->assertForbidden();
        $this->getJson('/api/approvals/students')->assertForbidden();
    }

    public function test_route_ids_must_have_the_matching_role(): void
    {
        $partner = $this->partner();
        $student = $this->student($this->bsit);

        $this->actingAs($this->admin, 'web');
        $this->postJson("/api/approvals/students/{$partner->id}/approve")->assertNotFound();
        $this->postJson("/api/approvals/partners/{$student->id}/approve")->assertNotFound();
        $this->postJson("/api/approvals/students/{$this->bsitCoordinator->id}/approve")->assertNotFound();
    }

    // Coordinator approves / returns

    public function test_a_coordinator_approves_their_programs_student(): void
    {
        $student = $this->student($this->bsit);

        $this->actingAs($this->bsitCoordinator, 'web')
            ->postJson("/api/approvals/students/{$student->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.review_note', null);

        $student->refresh();
        $this->assertSame(UserStatus::Active, $student->status);
        $this->assertSame($this->bsitCoordinator->id, $student->reviewed_by);
        $this->assertNotNull($student->reviewed_at);

        Notification::assertSentTo($student, RegistrationApproved::class, function (RegistrationApproved $n) use ($student) {
            return $n->toMail($student)->actionUrl === 'http://localhost:5173/login';
        });
    }

    // Admin: students

    public function test_admin_returns_a_student_who_is_later_approved(): void
    {
        $student = $this->student($this->bsab); // BSAB has no coordinator: admin only

        $this->actingAs($this->admin, 'web')
            ->postJson("/api/approvals/students/{$student->id}/return", ['reason' => self::RETURN_REASON])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review_note', self::RETURN_REASON);

        Notification::assertSentTo($student, RegistrationReturned::class,
            fn (RegistrationReturned $n) => $n->reason === self::RETURN_REASON);

        // Shows under the Returned filter, not Pending.
        $this->getJson('/api/approvals/students?status=rejected')->assertJsonPath('data.0.id', $student->id);
        $this->getJson('/api/approvals/students')->assertJsonCount(0, 'data');

        $this->postJson("/api/approvals/students/{$student->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.review_note', null);

        Notification::assertSentTo($student, RegistrationApproved::class);
    }

    public function test_return_requires_a_reason_of_10_to_500_characters(): void
    {
        $student = $this->student($this->bsit);
        $this->actingAs($this->admin, 'web');

        $this->postJson("/api/approvals/students/{$student->id}/return")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/approvals/students/{$student->id}/return", ['reason' => 'too short'])->assertJsonValidationErrors('reason');
        $this->postJson("/api/approvals/students/{$student->id}/return", ['reason' => str_repeat('x', 501)])->assertJsonValidationErrors('reason');

        $this->assertSame(UserStatus::Pending, $student->fresh()->status);
    }

    public function test_only_pending_registrations_can_be_returned_and_only_pending_or_returned_approved(): void
    {
        $active = $this->student($this->bsit, UserStatus::Active);
        $inactive = $this->student($this->bsit, UserStatus::Inactive);
        $rejected = $this->student($this->bsit, UserStatus::Rejected);
        $this->actingAs($this->admin, 'web');

        $this->postJson("/api/approvals/students/{$active->id}/return", ['reason' => self::RETURN_REASON])
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Only pending registrations can be returned.']);
        $this->postJson("/api/approvals/students/{$rejected->id}/return", ['reason' => self::RETURN_REASON])
            ->assertUnprocessable();
        $this->postJson("/api/approvals/students/{$inactive->id}/approve")
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Only pending or returned registrations can be approved.']);

        Notification::assertNothingSent();
    }

    public function test_index_filters_search_and_order(): void
    {
        $this->travel(-3)->days();
        $older = $this->student($this->bsit, user: ['name' => 'Carla Older']);
        $older->student->update(['student_number' => '2021-00001']);
        $this->travelBack();
        $newer = $this->student($this->bsab, user: ['name' => 'Ana Newer']);

        $this->actingAs($this->admin, 'web');

        $ids = fn (string $query) => collect($this->getJson('/api/approvals/students?'.$query)->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $ids(''));       // oldest first
        $this->assertSame([$older->id], $ids('search=2021-0000'));    // student number
        $this->assertSame([$newer->id], $ids('search=ana%20NEW'));    // name, case-insensitive
        $this->getJson('/api/approvals/students?status=active')->assertUnprocessable();
    }

    // Admin: partners

    public function test_admin_approves_and_returns_partners(): void
    {
        $first = $this->partner(profile: ['company_name' => 'Alpha Foods', 'city' => 'Mati City']);
        $second = $this->partner(profile: ['company_name' => 'Bravo Tech']);

        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/approvals/partners?search=mati')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_name', 'Alpha Foods')
            ->assertJsonStructure(['data' => [['id', 'email', 'company_name', 'documents', 'created_at', 'reviewed_at', 'review_note']]]);

        $this->postJson("/api/approvals/partners/{$first->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson("/api/approvals/partners/{$second->id}/return", ['reason' => 'Please upload your SEC or DTI registration.'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->postJson("/api/approvals/partners/{$second->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'active');

        Notification::assertSentTo($first, RegistrationApproved::class);
        Notification::assertSentTo($second, RegistrationReturned::class);
    }

    // Counts

    public function test_counts_per_role(): void
    {
        $this->student($this->bsit);
        $this->student($this->bsit);
        $this->student($this->bsab);
        $this->student($this->bsit, UserStatus::Rejected);
        $this->partner();

        $this->actingAs($this->admin, 'web')->getJson('/api/approvals/counts')
            ->assertOk()->assertExactJson(['students_pending' => 3, 'partners_pending' => 1]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->bsitCoordinator, 'web')->getJson('/api/approvals/counts')
            ->assertOk()->assertExactJson(['students_pending' => 2, 'partners_pending' => 0]);
    }

    // Documents

    public function test_document_download(): void
    {
        Storage::fake('local');
        $partner = $this->partner();
        $other = $this->partner();

        Storage::disk('local')->put('partner-documents/x/permit.pdf', '%PDF-1.4 test');
        $document = $partner->industryPartner->documents()->create([
            'type' => PartnerDocumentType::BusinessPermit,
            'original_name' => 'Mayor permit 2026.pdf',
            'path' => 'partner-documents/x/permit.pdf',
            'size' => 13,
        ]);

        $this->actingAs($this->admin, 'web');

        $response = $this->get("/api/approvals/partners/{$partner->id}/documents/{$document->id}");
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('Mayor permit 2026.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 test', $response->streamedContent());

        // Document belongs to a different partner.
        $this->getJson("/api/approvals/partners/{$other->id}/documents/{$document->id}")->assertNotFound();
        $this->getJson("/api/approvals/partners/{$partner->id}/documents/999")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->bsitCoordinator, 'web')
            ->getJson("/api/approvals/partners/{$partner->id}/documents/{$document->id}")
            ->assertForbidden();

        $this->assertInstanceOf(PartnerDocument::class, $document->fresh());
    }
}
