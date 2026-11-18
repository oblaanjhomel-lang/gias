<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * An academic term (config gias.terms): 1st Semester Aug–Dec, 2nd Semester Jan–May,
 * Midyear Jun–Jul. Key "2026-2027-1" = AY 2026–2027, 1st Semester.
 */
final readonly class AcademicTerm
{
    private function __construct(
        public int $ayStart,
        public int $number,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function forDate(CarbonInterface|string $date): self
    {
        $date = CarbonImmutable::parse($date instanceof CarbonInterface ? $date->toDateString() : $date)->startOfDay();
        $monthDay = $date->format('m-d');

        foreach (self::terms() as $number => $term) {
            if ($monthDay >= $term['start'] && $monthDay <= $term['end']) {
                // Terms that start earlier in the calendar year than term 1 belong to the AY that began the year before.
                $ayStart = $term['start'] < self::terms()[1]['start'] ? $date->year - 1 : $date->year;

                return self::make($ayStart, $number);
            }
        }

        throw new InvalidArgumentException("No academic term covers {$date->toDateString()}.");
    }

    public static function current(): self
    {
        return self::forDate(CarbonImmutable::now(config('gias.local_timezone')));
    }

    public static function fromKey(string $key): self
    {
        if (! preg_match('/^(\d{4})-(\d{4})-(\d)$/', $key, $m) || (int) $m[2] !== (int) $m[1] + 1 || ! isset(self::terms()[(int) $m[3]])) {
            throw new InvalidArgumentException("Invalid academic term key \"{$key}\".");
        }

        return self::make((int) $m[1], (int) $m[3]);
    }

    public static function isValidKey(string $key): bool
    {
        try {
            self::fromKey($key);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * The most recent terms first, starting at $from (default: the current term).
     *
     * @return list<self>
     */
    public static function list(int $count, ?self $from = null): array
    {
        $terms = [];
        $term = $from ?? self::current();
        for ($i = 0; $i < $count; $i++) {
            $terms[] = $term;
            $term = $term->previous();
        }

        return $terms;
    }

    public function previous(): self
    {
        $numbers = array_keys(self::terms());
        $index = array_search($this->number, $numbers, true);

        return $index === 0
            ? self::make($this->ayStart - 1, $numbers[count($numbers) - 1])
            : self::make($this->ayStart, $numbers[$index - 1]);
    }

    public function key(): string
    {
        return sprintf('%d-%d-%d', $this->ayStart, $this->ayStart + 1, $this->number);
    }

    /** "AY 2026–2027 · 1st Semester" */
    public function label(): string
    {
        return sprintf('AY %d–%d · %s', $this->ayStart, $this->ayStart + 1, self::terms()[$this->number]['label']);
    }

    /** "1st Sem 2026–27": compact, for chart axes. */
    public function shortLabel(): string
    {
        $name = str_replace('Semester', 'Sem', self::terms()[$this->number]['label']);

        return sprintf('%s %d–%02d', $name, $this->ayStart, ($this->ayStart + 1) % 100);
    }

    /**
     * @return array{key: string, label: string, short_label: string, start: string, end: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'short_label' => $this->shortLabel(),
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ];
    }

    private static function make(int $ayStart, int $number): self
    {
        $term = self::terms()[$number];
        $year = $term['start'] < self::terms()[1]['start'] ? $ayStart + 1 : $ayStart;

        return new self(
            $ayStart,
            $number,
            CarbonImmutable::parse("{$year}-{$term['start']}"),
            CarbonImmutable::parse("{$year}-{$term['end']}"),
        );
    }

    /**
     * @return array<int, array{label: string, start: string, end: string}>
     */
    private static function terms(): array
    {
        return config('gias.terms');
    }
}
