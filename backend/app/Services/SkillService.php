<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\IndustryPartner;
use App\Models\Skill;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The skills list (admin) and the skills a partner needs / a student has (own only).
 */
class SkillService
{
    /**
     * Admin: every skill with usage counts. Everyone else: active skills only.
     *
     * @return Collection<int, Skill>
     */
    public function list(User $viewer): Collection
    {
        return Skill::query()
            ->when(
                $viewer->role === UserRole::Admin,
                fn ($q) => $q->withCount(['partners', 'students']),
                fn ($q) => $q->where('is_active', true),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Skill
    {
        return Skill::create(['name' => $data['name'], 'is_active' => true])->loadCount(['partners', 'students']);
    }

    /**
     * @param  array{name?: string, is_active?: bool}  $data
     */
    public function update(Skill $skill, array $data): Skill
    {
        $skill->fill($data)->save();

        return $skill->loadCount(['partners', 'students']);
    }

    /**
     * Only unused skills can be deleted; used ones are deactivated instead (keeps history and pivots).
     */
    public function delete(Skill $skill): void
    {
        if ($skill->partners()->exists() || $skill->students()->exists()) {
            throw ValidationException::withMessages([
                'skill' => "“{$skill->name}” is used by partners or students, so it can’t be deleted. "
                    .'Deactivate it instead (is_active = false).',
            ]);
        }

        $skill->delete();
    }

    // Partner: skills we need

    /**
     * @return list<array{skill_id: int, name: string, importance: int}>
     */
    public function partnerSkills(User $partnerUser): array
    {
        return $this->partnerProfile($partnerUser)->skills()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Skill $s) => ['skill_id' => $s->id, 'name' => $s->name, 'importance' => (int) $s->pivot->importance])
            ->all();
    }

    /**
     * Replaces the partner's needs with $items.
     *
     * @param  list<array{skill_id: int, importance: int}>  $items
     * @return list<array{skill_id: int, name: string, importance: int}>
     */
    public function syncPartnerSkills(User $partnerUser, array $items): array
    {
        $profile = $this->partnerProfile($partnerUser);

        DB::transaction(fn () => $profile->skills()->sync(
            $this->keptInactive($profile->skills(), 'importance')
            + collect($items)->mapWithKeys(fn (array $i) => [(int) $i['skill_id'] => ['importance' => (int) $i['importance']]])->all(),
        ));

        return $this->partnerSkills($partnerUser);
    }

    // Student: my skills

    /**
     * @return list<array{skill_id: int, name: string, level: int}>
     */
    public function studentSkills(User $studentUser): array
    {
        return $this->studentProfile($studentUser)->skills()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Skill $s) => ['skill_id' => $s->id, 'name' => $s->name, 'level' => (int) $s->pivot->level])
            ->all();
    }

    /**
     * Replaces the student's skills with $items.
     *
     * @param  list<array{skill_id: int, level: int}>  $items
     * @return list<array{skill_id: int, name: string, level: int}>
     */
    public function syncStudentSkills(User $studentUser, array $items): array
    {
        $profile = $this->studentProfile($studentUser);

        DB::transaction(fn () => $profile->skills()->sync(
            $this->keptInactive($profile->skills(), 'level')
            + collect($items)->mapWithKeys(fn (array $i) => [(int) $i['skill_id'] => ['level' => (int) $i['level']]])->all(),
        ));

        return $this->studentSkills($studentUser);
    }

    /**
     * Links to DEACTIVATED skills aren't shown or edited, so a save keeps them (they come back
     * if an admin re-activates the skill).
     *
     * @return array<int, array<string, int>>
     */
    private function keptInactive(BelongsToMany $relation, string $pivotColumn): array
    {
        return $relation->where('is_active', false)->get()
            ->mapWithKeys(fn (Skill $s) => [$s->id => [$pivotColumn => (int) $s->pivot->{$pivotColumn}]])
            ->all();
    }

    private function partnerProfile(User $partnerUser): IndustryPartner
    {
        return $partnerUser->industryPartner()->firstOrFail();
    }

    private function studentProfile(User $studentUser): Student
    {
        return $studentUser->student()->firstOrFail();
    }
}
