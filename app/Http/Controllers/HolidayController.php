<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Support\HolidayCalendar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request, HolidayCalendar $calendar): View
    {
        $filters = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($filters['year'] ?? today()->year);
        $isAdmin = $request->user()->isAdmin();
        $ownerId = $isAdmin ? $request->user()->id : Employee::where('account_id', $request->user()->id)->value('user_id');

        return view('holidays.index', [
            'holidays' => Holiday::where('user_id', $ownerId)->whereYear('date', $year)->orderBy('date')->paginate(20)->withQueryString(),
            'nationalHolidays' => $calendar->nationalHolidays($year),
            'year' => $year,
            'isAdmin' => $isAdmin,
            'hasProfile' => $ownerId !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ]);
        if (Holiday::where('user_id', $request->user()->id)->whereDate('date', $data['date'])->exists()) {
            throw ValidationException::withMessages(['date' => 'A holiday already exists on this date.']);
        }
        try {
            $holiday = Holiday::create([...$data, 'user_id' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'A holiday already exists on this date.']);
        }

        return redirect()->route('holidays.index', ['year' => $holiday->date->year])->with('status', 'Holiday added.');
    }
}
