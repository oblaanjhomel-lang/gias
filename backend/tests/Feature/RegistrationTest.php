<?php

namespace Tests\Feature;

use App\Enums\GeocodeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewRegistrationPending;
use App\Notifications\RegistrationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Program $bsit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');

        $this->bsit = Program::create(['code' => 'BSIT', 'name' => 'BS Information Technology']);
        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    private function nominatimHit(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '7.4478', 'lon' => '125.8078']])]);
    }

    private function nominatimMiss(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);
    }

    /** @return array<string, mixed> */
    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@student.dnsc.edu.ph',
            'password' => 'student-pass-1',
            'password_confirmation' => 'student-pass-1',
            'student_number' => '2023-01234',
            'program_id' => $this->bsit->id,
            'year_level' => 3,
            'contact_number' => '0917 123 4567',
            'address' => 'Purok 3, Magugpo Poblacion',
            'city' => 'Tagum City',
            'province' => 'Davao del Norte',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function partnerPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Tagum Agri Ventures Inc.',
            'contact_person' => 'Liza Mercado',
            'email' => 'hr@tagumagri.ph',
            'contact_number' => '09171234567',
            'address' => 'Purok 3, Magugpo Poblacion',
            'city' => 'Tagum City',
            'province' => 'Davao del Norte',
            'intern_slots' => '4',
            'password' => 'partner-pass-1',
            'password_confirmation' => 'partner-pass-1',
        ], $overrides);
    }

    private function pdf(string $name = 'permit.pdf', int $kilobytes = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    // Students

    public function test_student_registration_creates_a_pending_account_and_notifies_the_coordinator(): void
    {
        $this->nominatimHit();
        $coordinator = User::factory()->role(UserRole::Coordinator)->create(['program_id' => $this->bsit->id]);

        $this->postJson('/api/register/student', $this->studentPayload())
            ->assertCreated()
            ->assertExactJson([
                'message' => "Your program's OJT Coordinator will review your registration. We'll email juan@student.dnsc.edu.ph once it has been reviewed.",
            ]);

        $user = User::where('email', 'juan@student.dnsc.edu.ph')->firstOrFail();
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame($this->bsit->id, $user->program_id);
        $this->assertGuest('web'); // no session for a pending account

        $student = $user->student;
        $this->assertSame('2023-01234', $student->student_number);
        $this->assertSame('09171234567', $student->contact_number);
        $this->assertSame(GeocodeStatus::Located, $student->geocode_status);
        $this->assertEqualsWithDelta(7.4478, $student->home_location->getLatitude(), 0.000001);

        Notification::assertSentTo($user, RegistrationReceived::class,
            fn (RegistrationReceived $n) => $n->reviewer === "your program's OJT Coordinator");
        Notification::assertSentTo($coordinator, NewRegistrationPending::class);
        Notification::assertNotSentTo($this->admin, NewRegistrationPending::class);
    }

    public function test_without_an_active_coordinator_the_admins_are_notified_and_a_failed_geocode_does_not_block(): void
    {
        $this->nominatimMiss();
        User::factory()->role(UserRole::Coordinator)->status(UserStatus::Inactive)->create(['program_id' => $this->bsit->id]);
        $inactiveAdmin = User::factory()->role(UserRole::Admin)->status(UserStatus::Inactive)->create();

        $this->postJson('/api/register/student', $this->studentPayload())
            ->assertCreated()
            ->assertJsonPath('message', fn (string $m) => str_starts_with($m, 'The GIAS Admin will review'));

        $user = User::where('email', 'juan@student.dnsc.edu.ph')->firstOrFail();
        $this->assertSame(GeocodeStatus::Failed, $user->student->geocode_status);
        $this->assertNull($user->student->home_location);

        Notification::assertSentTo($this->admin, NewRegistrationPending::class);
        Notification::assertNotSentTo($inactiveAdmin, NewRegistrationPending::class);
    }

    public function test_student_email_and_student_number_must_be_unique(): void
    {
        Student::factory()->create(['student_number' => '2023-01234']);
        User::factory()->create(['email' => 'juan@student.dnsc.edu.ph']);

        $this->postJson('/api/register/student', $this->studentPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'student_number']);
    }

    public function test_student_registration_validation(): void
    {
        $this->postJson('/api/register/student', $this->studentPayload([
            'password' => 'short',
            'password_confirmation' => 'different',
            'year_level' => 6,
            'program_id' => 999,
            'province' => 'Cebu',
            'contact_number' => '12345',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'password', 'year_level', 'program_id', 'province', 'contact_number',
        ]);
    }

    // Industry partners

    public function test_partner_registration_stores_documents_and_notifies_admins(): void
    {
        $this->nominatimHit();

        $this->post('/api/register/partner', $this->partnerPayload([
            'documents' => [
                ['file' => $this->pdf('SEC registration.pdf'), 'type' => 'sec_dti'],
                ['file' => $this->pdf('Mayor permit.pdf'), 'type' => 'business_permit'],
            ],
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', fn (string $m) => str_starts_with($m, 'The GIAS Admin will review'));

        $user = User::where('email', 'hr@tagumagri.ph')->firstOrFail();
        $this->assertSame(UserRole::Partner, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('Liza Mercado', $user->name);

        $partner = $user->industryPartner;
        $this->assertSame(4, $partner->intern_slots);
        $this->assertSame(GeocodeStatus::Located, $partner->geocode_status);

        $documents = $partner->documents()->orderBy('id')->get();
        $this->assertCount(2, $documents);
        $this->assertSame('SEC registration.pdf', $documents[0]->original_name);
        $this->assertSame('sec_dti', $documents[0]->type->value);
        $this->assertSame(200 * 1024, $documents[0]->size);
        $this->assertStringStartsWith("partner-documents/{$partner->id}/", $documents[0]->path);
        Storage::disk('local')->assertExists($documents[0]->path);
        Storage::disk('local')->assertExists($documents[1]->path);

        Notification::assertSentTo($user, RegistrationReceived::class);
        Notification::assertSentTo($this->admin, NewRegistrationPending::class);
    }

    public function test_partner_registration_without_documents_and_with_a_failed_geocode(): void
    {
        $this->nominatimMiss();

        $this->postJson('/api/register/partner', $this->partnerPayload())->assertCreated();

        $partner = User::where('email', 'hr@tagumagri.ph')->firstOrFail()->industryPartner;
        $this->assertSame(GeocodeStatus::Failed, $partner->geocode_status);
        $this->assertSame(0, $partner->documents()->count());
    }

    public function test_documents_must_be_pdfs_of_at_most_5_mb(): void
    {
        $this->post('/api/register/partner', $this->partnerPayload([
            'documents' => [
                ['file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'), 'type' => 'sec_dti'],
                ['file' => $this->pdf('huge.pdf', 5121), 'type' => 'business_permit'],
                ['file' => $this->pdf('ok.pdf'), 'type' => 'not-a-type'],
            ],
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'documents.0.file' => 'Each document must be a PDF.',
                'documents.1.file' => 'Each document must be 5 MB or smaller.',
                'documents.2.type',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'hr@tagumagri.ph']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_at_most_three_documents(): void
    {
        $documents = array_map(fn (int $i) => ['file' => $this->pdf("doc{$i}.pdf"), 'type' => 'other'], range(1, 4));

        $this->post('/api/register/partner', $this->partnerPayload(['documents' => $documents]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documents' => 'Upload at most 3 documents.']);
    }

    public function test_partner_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'hr@tagumagri.ph']);

        $this->postJson('/api/register/partner', $this->partnerPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    // Login after registering

    public function test_a_pending_registrant_cannot_sign_in(): void
    {
        $this->nominatimMiss();
        $this->postJson('/api/register/student', $this->studentPayload())->assertCreated();

        $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/auth/login', ['email' => 'juan@student.dnsc.edu.ph', 'password' => 'student-pass-1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is awaiting approval.');
    }

    // Throttle

    public function test_registration_is_throttled_to_three_per_minute(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/register/student', [])->assertUnprocessable();
        }

        $this->postJson('/api/register/partner', [])->assertTooManyRequests();

        // Separate counter: login is still available from the same IP.
        $this->withHeaders(['Referer' => 'http://localhost:5173'])
            ->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();

        $this->assertSame(0, DB::table('users')->where('role', 'student')->count());
    }
}
