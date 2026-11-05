<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
}
