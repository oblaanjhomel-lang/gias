<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\RegistrationApproved;
use App\Events\RegistrationReturned;
use App\Models\IndustryPartner;
use App\Models\PartnerDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review queues for self-registered Students (admin + their program's coordinator)
 * and Industry Partners (admin only). Who may act on a given student is decided by
 * UserPolicy::reviewStudent (route `can` middleware); the lists apply the same scope.
 */
class ApprovalService
{
    private const PER_PAGE = 10;

    public function __construct(private readonly AccountService $accounts) {}

    /**
     * @return array{students_pending: int, partners_pending: int}
     */
    public function counts(User $actor): array
    {
        return [
            'students_pending' => $this->studentsVisibleTo($actor)->where('status', UserStatus::Pending)->count(),
            'partners_pending' => $actor->role === UserRole::Admin
                ? User::query()->where('role', UserRole::Partner)->where('status', UserStatus::Pending)->count()
                : 0,
        ];
    }

    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function students(User $actor, array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');
        $like = $this->accounts->containsPattern($search);

        return $this->studentsVisibleTo($actor)
            ->with(['student', 'program'])
            ->where('status', $filters['status'] ?? UserStatus::Pending->value)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereLike('name', $like)
                ->orWhereLike('email', $like)
                ->orWhereHas('student', fn (Builder $s) => $s->whereLike('student_number', $like))))
            ->oldest()
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, IndustryPartner>
     */
    public function partners(array $filters): LengthAwarePaginator
    {
        $search = trim($filters['search'] ?? '');
        $like = $this->accounts->containsPattern($search);

        return IndustryPartner::query()
            ->with(['user', 'documents'])
            ->whereHas('user', fn (Builder $q) => $q
                ->where('role', UserRole::Partner)
                ->where('status', $filters['status'] ?? UserStatus::Pending->value))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereLike('company_name', $like)
                ->orWhereLike('city', $like)))
            ->oldest()
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function approveStudent(User $actor, User $student): User
    {
        $this->approve($actor, $student);

        return $student->load(['student', 'program']);
    }

    public function returnStudent(User $actor, User $student, string $reason): User
    {
        $this->return($actor, $student, $reason);

        return $student->load(['student', 'program']);
    }

    public function approvePartner(User $actor, User $partner): IndustryPartner
    {
        $this->approve($actor, $partner);

        return $this->partnerProfile($partner);
    }

    public function returnPartner(User $actor, User $partner, string $reason): IndustryPartner
    {
        $this->return($actor, $partner, $reason);

        return $this->partnerProfile($partner);
    }

    /**
     * Stream one of a partner's uploaded documents. 404 if it isn't theirs.
     */
    public function document(User $partner, PartnerDocument $document): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($document->industry_partner_id === $partner->industryPartner?->id, 404);
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * pending | rejected → active.
     */
    private function approve(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! in_array($locked->status, [UserStatus::Pending, UserStatus::Rejected], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or returned registrations can be approved.',
                ]);
            }

            $user->forceFill([
                'status' => UserStatus::Active,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => null,
            ])->save();
        });

        RegistrationApproved::dispatch($user); // listener emails the user (Module 8)
    }

    /**
     * pending → rejected, with the reason kept in review_note.
     */
    private function return(User $actor, User $user, string $reason): void
    {
        DB::transaction(function () use ($actor, $user, $reason) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->status !== UserStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending registrations can be returned.',
                ]);
            }

            $user->forceFill([
                'status' => UserStatus::Rejected,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ])->save();
        });

        RegistrationReturned::dispatch($user, $reason);
    }

    /**
     * Students the actor may review: all for an admin, own program for a coordinator.
     *
     * @return Builder<User>
     */
    private function studentsVisibleTo(User $actor): Builder
    {
        return User::query()
            ->where('role', UserRole::Student)
            ->when($actor->role !== UserRole::Admin, fn (Builder $q) => $actor->program_id !== null
                ? $q->where('program_id', $actor->program_id)
                : $q->whereRaw('false'));
    }

    private function partnerProfile(User $partner): IndustryPartner
    {
        return $partner->industryPartner()->with('documents')->firstOrFail()->setRelation('user', $partner);
    }
}
