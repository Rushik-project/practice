@extends('layouts.attendance')
@section('content')

    <h1>{{ $isAdmin ? 'Admin dashboard' : 'Employee dashboard' }}</h1>
    <p>{{ $isAdmin ? 'Review your team attendance and manage employees.' : 'View your attendance and record your working day.' }}</p>
    @if($isAdmin)
        <p><a href="{{ route('employees.index') }}">View employee list</a> &nbsp; <a href="{{ route('employees.index') }}#add-employee">Add employee</a></p>
    @elseif($total === 0)
        <div class="notice">Your account is not linked to an employee record. Ask your administrator to create an employee login for you.</div>
    @endif
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="stats">
        <div class="card"><span>{{ $isAdmin ? 'Total employees' : 'My employee profile' }}</span><strong>{{ $total }}</strong></div>
        <div class="card"><span>Checked in · {{ $date }}</span><strong>{{ $present }}</strong></div>
        <div class="card"><span>Not checked in</span><strong>{{ $total - $present }}</strong></div>
        <div class="card"><span>Completed day</span><strong>{{ $completed }}</strong></div>
    </div>
    <div>
        <section class="panel">
            <h2>Daily attendance</h2>
            <form method="GET" action="{{ route('attendance.index') }}" class="filters">
                @if($isAdmin)<div><label for="search">Employee or department</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Search your team" maxlength="100"></div>@endif
                <div><label for="date">Attendance date</label><input id="date" type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></div>
                <button>Apply filters</button><a href="{{ route('attendance.index') }}">Today</a>
            </form>
            <div class="table-wrap"><table>
                <thead><tr><th>Employee</th><th>Status</th><th>Check in</th><th>Check out</th><th>Hours</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    @php($record = $employee->attendances->first())
                    <tr>
                        <td><strong>{{ $employee->name }}</strong><small>{{ $employee->department }}</small><small>{{ $employee->email }}</small></td>
                        <td><span class="badge {{ $record ? ($record->checked_out_at ? 'complete' : 'present') : '' }}">{{ $record ? ($record->checked_out_at ? 'Completed' : 'Working') : 'Not checked in' }}</span></td>
                        <td>{{ $record?->checked_in_at->format('H:i') ?? '—' }}</td>
                        <td>{{ $record?->checked_out_at?->format('H:i') ?? '—' }}</td>
                        <td>{{ $record?->checked_out_at ? number_format($record->checked_in_at->diffInMinutes($record->checked_out_at) / 60, 2).' h' : '—' }}</td>
                        <td>
                            @if($date === today()->toDateString() && ! $record?->checked_out_at)
                                <form method="POST" action="{{ route($record ? 'attendance.check-out' : 'attendance.check-in', $employee) }}">@csrf<button aria-label="{{ $record ? 'Check out' : 'Check in' }} {{ $employee->name }}">{{ $record ? 'Check out' : 'Check in' }}</button></form>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $isAdmin ? 'No employees found. Add an employee to start tracking attendance.' : 'No employee profile linked yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $employees->links() }}</div>
            <p class="hint">Times use {{ config('app.timezone') }}. Check-in and check-out record the current time. Past dates are read-only.</p>
        </section>
    </div>
    @include('partials.holiday-calendar')
@endsection
