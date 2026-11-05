<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class HolidayCalendar
{
    public function nationalHolidays(int $year): Collection
    {
        if ($year === 2026) {
            return collect([
                ['name' => 'Makara Sankranthi', 'date' => CarbonImmutable::create(2026, 1, 14)->startOfDay(), 'type' => 'National'],
                ['name' => 'Republic Day', 'date' => CarbonImmutable::create(2026, 1, 26)->startOfDay(), 'type' => 'National'],
                ['name' => 'Ugadi', 'date' => CarbonImmutable::create(2026, 3, 19)->startOfDay(), 'type' => 'National'],
                ['name' => 'Id-ul-Fitr', 'date' => CarbonImmutable::create(2026, 3, 21)->startOfDay(), 'type' => 'National'],
                ['name' => 'Mahavir Jayanti', 'date' => CarbonImmutable::create(2026, 3, 31)->startOfDay(), 'type' => 'National'],
                ['name' => 'Good Friday', 'date' => CarbonImmutable::create(2026, 4, 3)->startOfDay(), 'type' => 'National'],
                ['name' => 'Buddha Purnima', 'date' => CarbonImmutable::create(2026, 5, 1)->startOfDay(), 'type' => 'National'],
                ['name' => 'Id-ul-Zuha (Bakrid)', 'date' => CarbonImmutable::create(2026, 5, 27)->startOfDay(), 'type' => 'National'],
                ['name' => 'Muharram', 'date' => CarbonImmutable::create(2026, 6, 26)->startOfDay(), 'type' => 'National'],
                ['name' => 'Independence Day', 'date' => CarbonImmutable::create(2026, 8, 15)->startOfDay(), 'type' => 'National'],
                ['name' => 'Milad Un Nabi or Id-E-Milad (Birthday of Prophet Mohammad)', 'date' => CarbonImmutable::create(2026, 8, 26)->startOfDay(), 'type' => 'National'],
                ['name' => 'Ganesh Chaturthi', 'date' => CarbonImmutable::create(2026, 9, 14)->startOfDay(), 'type' => 'National'],
                ['name' => "Mahatma Gandhi's Birthday", 'date' => CarbonImmutable::create(2026, 10, 2)->startOfDay(), 'type' => 'National'],
                ['name' => 'Dussehra (Vijay Dashmi)', 'date' => CarbonImmutable::create(2026, 10, 20)->startOfDay(), 'type' => 'National'],
                ['name' => 'Diwali (Deepavali)', 'date' => CarbonImmutable::create(2026, 11, 8)->startOfDay(), 'type' => 'National'],
                ['name' => "Guru Nanak's Birthday", 'date' => CarbonImmutable::create(2026, 11, 24)->startOfDay(), 'type' => 'National'],
                ['name' => 'Christmas Day', 'date' => CarbonImmutable::create(2026, 12, 25)->startOfDay(), 'type' => 'National'],
            ]);
        }

        return collect([
            ['name' => 'Republic Day', 'date' => CarbonImmutable::create($year, 1, 26)->startOfDay(), 'type' => 'National'],
            ['name' => 'Independence Day', 'date' => CarbonImmutable::create($year, 8, 15)->startOfDay(), 'type' => 'National'],
            ['name' => 'Gandhi Jayanti', 'date' => CarbonImmutable::create($year, 10, 2)->startOfDay(), 'type' => 'National'],
        ]);
    }

    public function month(User $user, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $gridStart = $start->startOfWeek();
        $gridEnd = $start->endOfMonth()->endOfWeek();
        $ownerId = $user->isAdmin() ? $user->id : Employee::where('account_id', $user->id)->value('user_id');
        $events = Holiday::where('user_id', $ownerId)->whereBetween('date', [$gridStart->toDateString(), $gridEnd->endOfDay()->toDateTimeString()])
            ->orderBy('date')->get()->map(fn (Holiday $holiday) => ['name' => $holiday->name, 'date' => $holiday->date, 'type' => 'Company']);
        foreach (range($gridStart->year, $gridEnd->year) as $year) {
            $events = $events->concat($this->nationalHolidays($year));
        }
        $byDate = $events->groupBy(fn (array $event) => $event['date']->toDateString());
        $days = [];
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $days[] = ['date' => $day, 'currentMonth' => $day->month === $start->month, 'events' => $byDate->get($day->toDateString(), collect())];
        }

        return [
            'month' => $start,
            'weeks' => array_chunk($days, 7),
            'previous' => $start->year === 2000 && $start->month === 1 ? null : $start->subMonth()->format('Y-m'),
            'next' => $start->year === 2100 && $start->month === 12 ? null : $start->addMonth()->format('Y-m'),
            'nationalHolidays' => $this->nationalHolidays($start->year),
        ];
    }
}
