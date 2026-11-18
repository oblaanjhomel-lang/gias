<?php

namespace App\Services;

use App\Enums\GeocodeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\IndustryPartner;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IndustryPartnerService
{
    public function __construct(
        private readonly IGeocodingService $geocoder,
        private readonly AccountService $accounts,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, IndustryPartner>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');
        $like = $this->accounts->containsPattern($search);

        return IndustryPartner::query()
            ->with('user')
            ->whereHas('user', fn (Builder $q) => $q
                ->where('role', UserRole::Partner)
                ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status)))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereLike('company_name', $like)
                ->orWhereLike('contact_person', $like)
                ->orWhereLike('city', $like)
                ->orWhereLike('province', $like)
                ->orWhereHas('user', fn (Builder $u) => $u->whereLike('email', $like))))
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data  validated StoreIndustryPartnerRequest data
     */
    public function create(array $data): IndustryPartner
    {
        $partner = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'password' => $this->accounts->randomPassword(),
                'role' => UserRole::Partner,
                'status' => UserStatus::Active,
            ]);

            return $user->industryPartner()->create($this->profileFields($data) + [
                'geocode_status' => GeocodeStatus::Pending,
            ]);
        });

        // Outside the transaction: an HTTP call (up to 10s) shouldn't hold a DB transaction open,
        // and a failed lookup must not roll back the account.
        $this->locate($partner);

        $partner->load('user');
        $this->accounts->sendSetPasswordLink($partner->user);

        return $partner;
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateIndustryPartnerRequest data
     */
    public function update(User $actor, User $partnerUser, array $data): IndustryPartner
    {
        $this->accounts->ensureManageable($actor, $partnerUser);

        $profile = $partnerUser->industryPartner()->firstOrFail();
        $manual = isset($data['latitude'], $data['longitude']);

        $addressChanged = DB::transaction(function () use ($partnerUser, $profile, $data, $manual) {
            $partnerUser->fill([
                'name' => $data['contact_person'],
                'email' => $data['email'],
            ])->save();

            $profile->fill($this->profileFields($data));
            $addressChanged = $profile->isDirty(['address', 'city', 'province']);

            if ($manual) {
                $profile->location = Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude']);
                $profile->geocode_status = GeocodeStatus::Manual;
            }

            $profile->save();

            if (isset($data['status']) && UserStatus::from($data['status']) !== $partnerUser->status) {
                $this->accounts->applyStatus($partnerUser, UserStatus::from($data['status']));
            }

            return $addressChanged;
        });

        if ($addressChanged && ! $manual) {
            $this->locate($profile);
        }

        return $profile->setRelation('user', $partnerUser);
    }

    public function setStatus(User $actor, User $partnerUser, UserStatus $status): IndustryPartner
    {
        $this->accounts->changeStatus($actor, $partnerUser, $status);

        return $partnerUser->industryPartner()->firstOrFail()->setRelation('user', $partnerUser);
    }

    public function delete(User $actor, User $partnerUser): void
    {
        // deployments.industry_partner_id is restrictOnDelete: keep placement history intact.
        if ($partnerUser->industryPartner?->deployments()->exists()) {
            throw ValidationException::withMessages([
                'partner' => 'This partner has deployment records, so it can’t be deleted. Deactivate it instead.',
            ]);
        }

        $this->accounts->delete($actor, $partnerUser);
    }

    /**
     * Geocode the profile's address. On a miss the old point is cleared, since it belonged
     * to a different address.
     */
    private function locate(IndustryPartner $profile): void
    {
        $hit = $this->geocoder->geocode($profile->address, $profile->city, $profile->province->value);

        $profile->location = $hit ? Point::makeGeodetic($hit['lat'], $hit['lng']) : null;
        $profile->geocode_status = $hit ? GeocodeStatus::Located : GeocodeStatus::Failed;
        $profile->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileFields(array $data): array
    {
        return [
            'company_name' => $data['company_name'],
            'contact_person' => $data['contact_person'],
            'contact_number' => $data['contact_number'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'province' => $data['province'],
            'intern_slots' => $data['intern_slots'],
        ];
    }
}
