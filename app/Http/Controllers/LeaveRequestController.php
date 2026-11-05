<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function employee(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);
        $employee = Employee::where('account_id', $request->user()->id)->first();

        return view('leave.index', [
            'isAdmin' => false,
            'employee' => $employee,
            'leaves' => LeaveRequest::whereHas('employee', fn ($query) => $query->where('account_id', $request->user()->id))
                ->with('employee')->latest()->paginate(15),
        ]);
    }

    public function admin(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('leave.index', [
            'isAdmin' => true,
            'employee' => null,
            'leaves' => LeaveRequest::whereHas('employee', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with('employee')->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $employee = Employee::where('account_id', $request->user()->id)->lockForUpdate()->first();
            if (! $employee) {
                throw ValidationException::withMessages(['leave' => 'Ask your administrator to link your employee profile before applying for leave.']);
            }
            $overlaps = $employee->leaveRequests()->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists();
            if ($overlaps) {
                throw ValidationException::withMessages(['start_date' => 'These dates overlap an existing pending or approved leave request.']);
            }
            $employee->leaveRequests()->create($data);
        });

        return redirect()->route('employee.leave')->with('status', 'Leave request submitted to your administrator.');
    }

    public function review(Request $request, int $leave): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $leave = LeaveRequest::whereHas('employee', fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($leave);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $updated = LeaveRequest::whereKey($leave->id)->where('status', 'pending')->update([
            'status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        if (! $updated) {
            throw ValidationException::withMessages(['leave' => 'This leave request has already been reviewed.']);
        }

        return redirect()->route('admin.leave')->with('status', 'Leave request '.$data['status'].'.');
    }
}
