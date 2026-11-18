<?php

namespace App\Services;

use App\Enums\GeocodeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\RegistrationSubmitted;
use App\Models\IndustryPartner;
use App\Models\Student;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Self-registration for Students and Industry Partners. New accounts start as `pending`.
 */
class RegistrationService
{
    public function __construct(
        private readonly IGeocodingService $geocoder,
        private readonly RecipientService $recipients,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StudentRegistrationRequest data
     * @return string confirmation message for the registrant
     */
    public function registerStudent(array $data): string
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Student,
                'status' => UserStatus::Pending,
                'program_id' => $data['program_id'],
            ]);

            $user->student()->create([
                'student_number' => $data['student_number'],
                'year_level' => $data['year_level'],
                'contact_number' => $data['contact_number'],
                'address' => $data['address'],
                'city' => $data['city'],
                'province' => $data['province'],
                'geocode_status' => GeocodeStatus::Pending,
            ]);

            return $user;
        });

        // After commit: geocoding is an HTTP call and never blocks registration.
        $this->locate($user->student, 'home_location');

        // Listeners email the registrant and notify the reviewers (Module 8).
        RegistrationSubmitted::dispatch($user);

        return $this->confirmation($this->recipients->registrationReviewers($user)['label'], $user->email);
    }

    /**
     * @param  array<string, mixed>  $data  validated PartnerRegistrationRequest data
     * @return string confirmation message for the registrant
     */
    public function registerPartner(array $data): string
    {
        $storedPaths = [];

        try {
            $user = DB::transaction(function () use ($data, &$storedPaths) {
                $user = User::create([
                    'name' => $data['contact_person'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => UserRole::Partner,
                    'status' => UserStatus::Pending,
                ]);

                $partner = $user->industryPartner()->create([
                    'company_name' => $data['company_name'],
                    'contact_person' => $data['contact_person'],
                    'contact_number' => $data['contact_number'] ?? null,
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'province' => $data['province'],
                    'intern_slots' => $data['intern_slots'],
                    'geocode_status' => GeocodeStatus::Pending,
                ]);

                foreach ($data['documents'] ?? [] as $document) {
                    $storedPaths[] = $this->storeDocument($partner, $document['file'], $document['type']);
                }

                return $user;
            });
        } catch (Throwable $e) {
            // The rows were rolled back; don't leave their files behind.
            Storage::disk('local')->delete($storedPaths);

            throw $e;
        }

        $this->locate($user->industryPartner, 'location');

        RegistrationSubmitted::dispatch($user);

        return $this->confirmation($this->recipients->registrationReviewers($user)['label'], $user->email);
    }

    /**
     * Saves the PDF on the private `local` disk and records it. Returns the stored path.
     */
    private function storeDocument(IndustryPartner $partner, UploadedFile $file, string $type): string
    {
        $path = $file->storeAs("partner-documents/{$partner->id}", Str::uuid().'.pdf', 'local');

        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded document.');
        }

        // Slashes would break the download's Content-Disposition header.
        $originalName = Str::limit(str_replace(['/', '\\'], '-', $file->getClientOriginalName()), 255, '');

        $partner->documents()->create([
            'type' => $type,
            'original_name' => $originalName,
            'path' => $path,
            'size' => $file->getSize(),
        ]);

        return $path;
    }

    /**
     * Geocode a profile's address into $column; marks located or failed, never throws.
     */
    private function locate(Student|IndustryPartner $profile, string $column): void
    {
        $hit = $this->geocoder->geocode($profile->address, $profile->city, $profile->province->value);

        $profile->{$column} = $hit ? Point::makeGeodetic($hit['lat'], $hit['lng']) : null;
        $profile->geocode_status = $hit ? GeocodeStatus::Located : GeocodeStatus::Failed;
        $profile->save();
    }

    private function confirmation(string $reviewer, string $email): string
    {
        return ucfirst($reviewer)." will review your registration. We'll email {$email} once it has been reviewed.";
    }
}
