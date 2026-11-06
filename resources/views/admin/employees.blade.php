@extends('layouts.attendance')
@section('content')
    <h1>Employee list</h1>
    <p>Manage your employees and create their login accounts.</p>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="layout">
        <section class="panel">
            <h2>Your employees</h2>
            <form method="GET" action="{{ route('employees.index') }}" class="filters">
                <div><label for="search">Name, email or department</label><input id="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Search employees"></div>
                <button>Search</button><a href="{{ route('employees.index') }}">Reset</a>
            </form>
            <div class="table-wrap"><table>
                <thead><tr><th>Employee</th><th>Department</th><th>Daily target</th><th>Employee login</th><th>Details</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td><strong>{{ $employee->name }}</strong><small>{{ $employee->email }}</small></td>
                        <td>{{ $employee->department }}</td>
                        <td>
                            <form method="POST" action="{{ route('employees.working-hours', $employee) }}">
                                @csrf
                                @method('PATCH')
                                @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                                <label for="daily-working-hours-{{ $employee->id }}">Hours per day</label>
                                <input id="daily-working-hours-{{ $employee->id }}" type="number" name="daily_working_hours" min="1" max="24" step="0.25" value="{{ $employee->daily_working_hours }}" required>
                                <button>Update target</button>
                            </form>
                        </td>
                        <td>
                            @if($employee->account_id)
                                <span class="badge present">Account active</span>
                            @else
                                <details><summary>Create login</summary>
                                    <form method="POST" action="{{ route('employees.account', $employee) }}" style="margin-top:16px;min-width:220px">
                                        @csrf
                                        <div class="field"><label for="account-email-{{ $employee->id }}">Login email</label><input id="account-email-{{ $employee->id }}" type="email" name="account_email" value="{{ $employee->email }}" required maxlength="255"></div>
                                        <div class="field"><label for="password-{{ $employee->id }}">Password</label><input id="password-{{ $employee->id }}" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
                                        <div class="field"><label for="confirm-{{ $employee->id }}">Confirm password</label><input id="confirm-{{ $employee->id }}" type="password" name="password_confirmation" required autocomplete="new-password"></div>
                                        <button>Create employee login</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td>
                            <details>
                                <summary>Edit details</summary>
                                <form method="POST" action="{{ route('employees.update', $employee) }}" style="margin-top:16px;min-width:220px">
                                    @csrf
                                    @method('PATCH')
                                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                                    <div class="field"><label for="employee-name-{{ $employee->id }}">Full name</label><input id="employee-name-{{ $employee->id }}" name="name" value="{{ $employee->name }}" maxlength="255" required></div>
                                    <div class="field"><label for="employee-email-{{ $employee->id }}">Email</label><input id="employee-email-{{ $employee->id }}" type="email" name="email" value="{{ $employee->email }}" maxlength="255" required></div>
                                    <div class="field"><label for="employee-department-{{ $employee->id }}">Department</label><input id="employee-department-{{ $employee->id }}" name="department" value="{{ $employee->department }}" maxlength="255" required></div>
                                    <button type="submit">Save details</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No employees found. Add your first employee using the form.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $employees->links() }}</div>
        </section>
        <aside class="panel" id="add-employee">
            <h2>Add employee</h2>
            <form method="POST" action="{{ route('employees.store') }}">
                @csrf
                <div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
                <div class="field"><label for="email">Email / login email</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" required></div>
                <div class="field"><label for="department">Department</label><input id="department" name="department" value="{{ old('department') }}" maxlength="255" required></div>
                <div class="field"><label for="password">Login password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
                <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <button>Add employee</button>
            </form>
            <p class="hint">The employee can sign in using this email and password to access their own attendance page.</p>
        </aside>
    </div>
@endsection
