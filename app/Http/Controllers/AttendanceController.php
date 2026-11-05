<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Support\HolidayCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'employees' => $employees->with(['attendances' => fn ($query) => $query->whereDate('date', $date)])
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
        $updated = $employee->attendances()->whereDate('date', today())->whereNull('checked_out_at')
            ->update(['checked_out_at' => now()]);
        if (! $updated) {
            throw ValidationException::withMessages(['attendance' => 'Check in first. Completed attendance cannot be checked out again.']);
        }

        return redirect()->route($request->user()->dashboardRoute())->with('status', 'Check-out recorded.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
