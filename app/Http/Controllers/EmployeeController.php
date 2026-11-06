<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $employees = Employee::where('user_id', $request->user()->id);
        if (! empty($filters['search'])) {
            $employees->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%')
                    ->orWhere('department', 'like', '%'.$filters['search'].'%');
            });
        }

        return view('admin.employees', ['employees' => $employees->orderBy('name')->paginate(15)->withQueryString()]);
    }

    public function colleagues(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);
        $employee = Employee::where('account_id', $request->user()->id)->firstOrFail();
        $colleagues = Employee::where('user_id', $employee->user_id)
            ->where('id', '!=', $employee->id)
            ->with('account:id,profile_photo_path')
            ->orderBy('name')
            ->paginate(24);
        $photoUrls = $colleagues->getCollection()->mapWithKeys(fn (Employee $colleague): array => [
            $colleague->id => $colleague->account?->profile_photo_path
                ? Storage::disk('public')->url($colleague->account->profile_photo_path)
                : null,
        ]);

        return view('employee.colleagues', [
            'colleagues' => $colleagues,
            'photoUrls' => $photoUrls,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', Rule::unique('employees')->where('user_id', $request->user()->id)],
            'department' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $account = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            Employee::create([
                'name' => $data['name'], 'email' => $data['email'], 'department' => $data['department'],
                'user_id' => $request->user()->id, 'account_id' => $account->id,
            ]);
        });

        return redirect()->route('employees.index')->with('status', 'Employee and login account created. Share the login details with your employee.');
    }

    public function createAccount(Request $request, int $employee): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $employee = Employee::where('user_id', $request->user()->id)->findOrFail($employee);
        abort_if($employee->account_id !== null, 409, 'This employee already has a login account.');
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
            'account_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        DB::transaction(function () use ($employee, $data): void {
            $locked = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->account_id !== null, 409);
            $account = User::create(['name' => $locked->name, 'email' => $data['account_email'], 'password' => $data['password']]);
            $locked->update(['account_id' => $account->id]);
        });

        return redirect()->route('employees.index')->with('status', 'Employee login account created.');
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $employee = Employee::where('user_id', $request->user()->id)->findOrFail($employee);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->where('user_id', $request->user()->id)->ignore($employee->id),
                Rule::unique('users', 'email')->ignore($employee->account_id),
            ],
            'department' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($employee, $data): void {
            $employee->update($data);
            if ($employee->account_id) {
                User::whereKey($employee->account_id)->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            }
        });

        return redirect()->route('employees.index', $request->only('search'))
            ->with('status', 'Employee details updated.');
    }

    public function updateWorkingHours(Request $request, int $employee): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $employee = Employee::where('user_id', $request->user()->id)->findOrFail($employee);
        $data = $request->validate([
            'daily_working_hours' => ['required', 'numeric', 'between:1,24', 'decimal:0,2', 'multiple_of:0.25'],
        ]);
        $employee->update($data);

        return redirect()->route('employees.index', $request->only('search'))
            ->with('status', 'Daily working hours updated.');
    }
}
