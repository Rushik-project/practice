<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Support\HolidayCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function admin(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return $this->index($request);
    }

    public function employee(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);

        return $this->index($request);
    }

    public function monthlySummary(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        [$month, $monthStart, $reportEnd] = $this->validatedReportPeriod($request);
        $workingDays = $this->workingDays($request->user(), $month, $monthStart, $reportEnd);

        $employees = Employee::where('user_id', $request->user()->id)
            ->with(['attendances' => fn ($query) => $query
                ->whereBetween('date', [$monthStart->toDateString(), $reportEnd->toDateString()])
                ->select('id', 'employee_id', 'date')])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
        $summaries = $employees->getCollection()->map(function (Employee $employee) use ($workingDays): array {
            $eligibleDays = $workingDays->filter(
                fn (CarbonImmutable $day): bool => $day->toDateString() >= $employee->created_at->toDateString(),
            );
            $presentDays = $employee->attendances
                ->pluck('date')
                ->map(fn (string $date): string => CarbonImmutable::parse($date)->toDateString())
                ->all();
            $present = $eligibleDays->filter(
                fn (CarbonImmutable $day): bool => in_array($day->toDateString(), $presentDays, true),
            )->count();
            $absentDates = $eligibleDays
                ->reject(fn (CarbonImmutable $day): bool => in_array($day->toDateString(), $presentDays, true))
                ->map(fn (CarbonImmutable $day): string => $day->format('d M Y'))
                ->values()
                ->all();
            $workingDayCount = $eligibleDays->count();

            return [
                'employee' => $employee,
                'working_days' => $workingDayCount,
                'present' => $present,
                'absent' => $workingDayCount - $present,
                'absent_dates' => $absentDates,
            ];
        });

        return view('admin.attendance-summary', [
            'summaries' => $summaries,
            'employees' => $employees,
            'month' => $month,
            'workingDays' => $workingDays->count(),
            'present' => $summaries->sum('present'),
            'absent' => $summaries->sum('absent'),
        ]);
    }

    public function employeeMonthlySummary(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);

        [$month, $monthStart, $reportEnd] = $this->validatedReportPeriod($request);
        $workingDays = $this->workingDays($request->user(), $month, $monthStart, $reportEnd);
        $employee = Employee::where('account_id', $request->user()->id)->first();
        $summary = [
            'working_days' => 0,
            'present' => 0,
            'absent' => 0,
            'worked_seconds' => 0,
        ];

        if ($employee) {
            $eligibleDays = $workingDays->filter(
                fn (CarbonImmutable $day): bool => $day->toDateString() >= $employee->created_at->toDateString(),
            );
            $attendances = $employee->attendances()
                ->whereBetween('date', [$monthStart->toDateString(), $reportEnd->toDateString()])
                ->with('breaks')
                ->get();
            $presentDays = $attendances
                ->pluck('date')
                ->map(fn (string $date): string => CarbonImmutable::parse($date)->toDateString())
                ->all();
            $present = $eligibleDays->filter(
                fn (CarbonImmutable $day): bool => in_array($day->toDateString(), $presentDays, true),
            )->count();

            $summary = [
                'working_days' => $eligibleDays->count(),
                'present' => $present,
                'absent' => $eligibleDays->count() - $present,
                'worked_seconds' => $attendances->sum(fn (Attendance $attendance): int => $attendance->workedSeconds()),
            ];
        }

        return view('employee.attendance-summary', [
            'employee' => $employee,
            'summary' => $summary,
            'month' => $month,
        ]);
    }

    /**
     * @return array{string, CarbonImmutable, CarbonImmutable}
     */
    private function validatedReportPeriod(Request $request): array
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01'],
        ]);
        $month = $filters['month'] ?? today()->format('Y-m');
        $monthStart = CarbonImmutable::createFromFormat('!Y-m', $month);
        if ($monthStart->greaterThan(today()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'The report month cannot be in the future.']);
        }

        $reportEnd = $monthStart->isSameMonth(today())
            ? CarbonImmutable::instance(today())->startOfDay()
            : $monthStart->endOfMonth();

        return [$month, $monthStart, $reportEnd];
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function workingDays(User $user, string $month, CarbonImmutable $monthStart, CarbonImmutable $reportEnd): Collection
    {
        $calendar = app(HolidayCalendar::class)->month($user, $month);
        $holidayDates = collect($calendar['weeks'])
            ->flatten(1)
            ->filter(fn (array $day): bool => $day['currentMonth'] && $day['events']->isNotEmpty())
            ->map(fn (array $day): string => $day['date']->toDateString())
            ->all();
        $workingDays = collect();

        for ($day = $monthStart; $day->lte($reportEnd); $day = $day->addDay()) {
            if ($day->dayOfWeekIso <= 6 && ! in_array($day->toDateString(), $holidayDates, true)) {
                $workingDays->push($day);
            }
        }

        return $workingDays;
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'search' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
        ]);
        $date = $filters['date'] ?? today()->toDateString();
        $isAdmin = $request->user()->isAdmin();
        $ownerColumn = $isAdmin ? 'user_id' : 'account_id';
        $employees = Employee::where($ownerColumn, $request->user()->id);
        $total = (clone $employees)->count();
        $present = Attendance::whereHas('employee', fn ($query) => $query->where($ownerColumn, $request->user()->id))
            ->whereDate('date', $date)->count();
        $completed = Attendance::whereHas('employee', fn ($query) => $query->where($ownerColumn, $request->user()->id))
            ->whereDate('date', $date)->whereNotNull('checked_out_at')->count();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $employees->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('department', 'like', '%'.$search.'%');
            });
        }

        return view('attendance.index', [
            'employees' => $employees->with(['attendances' => fn ($query) => $query->whereDate('date', $date)->with('breaks')])
                ->orderBy('name')->paginate(15)->withQueryString(),
            'date' => $date,
            'total' => $total,
            'present' => $present,
            'completed' => $completed,
            'isAdmin' => $isAdmin,
            'calendar' => app(HolidayCalendar::class)->month($request->user(), $filters['month'] ?? substr($date, 0, 7)),
        ]);
    }

    public function checkIn(Request $request, int $employee): RedirectResponse
    {
        $employee = Employee::where($request->user()->isAdmin() ? 'user_id' : 'account_id', $request->user()->id)->findOrFail($employee);
        $attendance = $employee->attendances()->firstOrCreate(
            ['date' => today()->toDateString()],
            ['checked_in_at' => now()],
        );
        if (! $attendance->wasRecentlyCreated) {
            throw ValidationException::withMessages(['attendance' => 'This employee has already checked in today.']);
        }

        return redirect()->route($request->user()->dashboardRoute())->with('status', 'Check-in recorded.');
    }

    public function checkOut(Request $request, int $employee): RedirectResponse
    {
        $employee = Employee::where($request->user()->isAdmin() ? 'user_id' : 'account_id', $request->user()->id)->findOrFail($employee);
        DB::transaction(function () use ($employee): void {
            $attendance = $employee->attendances()->whereDate('date', today())->lockForUpdate()->first();
            if (! $attendance || $attendance->checked_out_at) {
                throw ValidationException::withMessages(['attendance' => 'Check in first. Completed attendance cannot be checked out again.']);
            }

            if ($attendance->breaks()->whereNull('break_ended_at')->exists()) {
                throw ValidationException::withMessages(['attendance' => 'End your break before checking out.']);
            }

            $attendance->update(['checked_out_at' => now()]);
        });

        return redirect()->route($request->user()->dashboardRoute())->with('status', 'Check-out recorded.');
    }

    public function breakIn(Request $request, int $employee): RedirectResponse
    {
        $employee = Employee::where($request->user()->isAdmin() ? 'user_id' : 'account_id', $request->user()->id)->findOrFail($employee);
        DB::transaction(function () use ($employee): void {
            $attendance = $employee->attendances()->whereDate('date', today())->lockForUpdate()->first();
            if (! $attendance || $attendance->checked_out_at) {
                throw ValidationException::withMessages(['attendance' => 'Check in before starting a break.']);
            }

            if ($attendance->breaks()->whereNull('break_ended_at')->exists()) {
                throw ValidationException::withMessages(['attendance' => 'You are already on a break.']);
            }

            $attendance->breaks()->create(['break_started_at' => now()]);
        });

        return redirect()->route($request->user()->dashboardRoute())->with('status', 'Break started.');
    }

    public function breakOut(Request $request, int $employee): RedirectResponse
    {
        $employee = Employee::where($request->user()->isAdmin() ? 'user_id' : 'account_id', $request->user()->id)->findOrFail($employee);
        DB::transaction(function () use ($employee): void {
            $attendance = $employee->attendances()->whereDate('date', today())->lockForUpdate()->first();
            if (! $attendance || $attendance->checked_out_at) {
                throw ValidationException::withMessages(['attendance' => 'Check in before ending a break.']);
            }

            $break = $attendance->breaks()->whereNull('break_ended_at')->lockForUpdate()->first();
            if (! $break) {
                throw ValidationException::withMessages(['attendance' => 'Start a break before ending it.']);
            }

            $break->update(['break_ended_at' => now()]);
        });

        return redirect()->route($request->user()->dashboardRoute())->with('status', 'Break ended.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
