<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AcademicTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicTermTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function boundaries(): array
    {
        return [
            'Jul 31: midyear of the AY that began the year before' => ['2026-07-31', '2025-2026-3', 'AY 2025–2026 · Midyear'],
            'Aug 1: first semester starts the AY' => ['2026-08-01', '2026-2027-1', 'AY 2026–2027 · 1st Semester'],
            'Dec 31: still first semester' => ['2026-12-31', '2026-2027-1', 'AY 2026–2027 · 1st Semester'],
            'Jan 1: second semester, same AY' => ['2027-01-01', '2026-2027-2', 'AY 2026–2027 · 2nd Semester'],
            'May 31: still second semester' => ['2027-05-31', '2026-2027-2', 'AY 2026–2027 · 2nd Semester'],
            'Jun 1: midyear, same AY' => ['2027-06-01', '2026-2027-3', 'AY 2026–2027 · Midyear'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_term_for_date(string $date, string $key, string $label): void
    {
        $term = AcademicTerm::forDate($date);

        $this->assertSame($key, $term->key());
        $this->assertSame($label, $term->label());
        $this->assertTrue($term->start->toDateString() <= $date && $date <= $term->end->toDateString());
    }

    public function test_dates_previous_list_and_keys(): void
    {
        $this->travelTo('2026-11-17 02:00:00');
        $current = AcademicTerm::current();

        $this->assertSame(['2026-08-01', '2026-12-31'], [$current->start->toDateString(), $current->end->toDateString()]);
        $this->assertSame(['2027-01-01', '2027-05-31'], [AcademicTerm::fromKey('2026-2027-2')->start->toDateString(), AcademicTerm::fromKey('2026-2027-2')->end->toDateString()]);
        $this->assertSame(['2027-06-01', '2027-07-31'], [AcademicTerm::fromKey('2026-2027-3')->start->toDateString(), AcademicTerm::fromKey('2026-2027-3')->end->toDateString()]);

        $this->assertSame(
            ['2026-2027-1', '2025-2026-3', '2025-2026-2', '2025-2026-1', '2024-2025-3'],
            array_map(fn (AcademicTerm $t) => $t->key(), AcademicTerm::list(5)),
        );
        $this->assertSame('2026-2027-2', AcademicTerm::fromKey('2026-2027-2')->key());
        $this->assertFalse(AcademicTerm::isValidKey('2026-2028-1'));
        $this->assertFalse(AcademicTerm::isValidKey('2026-2027-4'));

        $this->expectException(InvalidArgumentException::class);
        AcademicTerm::fromKey('nope');
    }

    public function test_terms_endpoint_for_any_signed_in_user(): void
    {
        $this->travelTo('2026-11-17 02:00:00');

        $this->getJson('/api/terms')->assertUnauthorized();

        $this->actingAs(User::factory()->role(UserRole::Student)->create())
            ->getJson('/api/terms')
            ->assertOk()
            ->assertJsonPath('data.current.key', '2026-2027-1')
            ->assertJsonPath('data.current.label', 'AY 2026–2027 · 1st Semester')
            ->assertJsonCount(8, 'data.terms')
            ->assertJsonPath('data.terms.1.key', '2025-2026-3');
    }
}
