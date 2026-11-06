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
                <thead><tr><th>Employee</th><th>Status</th><th>Check in</th><th>Check out</th><th>Hours</th><th>Timer</th><th>Daily target</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    @php($record = $employee->attendances->first())
                    <tr>
                        <td><strong>{{ $employee->name }}</strong><small>{{ $employee->department }}</small><small>{{ $employee->email }}</small></td>
                        @php($openBreak = $record?->breaks->firstWhere('break_ended_at', null))
                        <td><span class="badge {{ $record ? ($record->checked_out_at ? 'complete' : ($openBreak ? '' : 'present')) : '' }}">{{ $record ? ($record->checked_out_at ? 'Completed' : ($openBreak ? 'On break' : 'Working')) : 'Not checked in' }}</span></td>
                        <td>{{ $record?->checked_in_at->format('H:i:s') ?? '—' }}</td>
                        <td>{{ $record?->checked_out_at?->format('H:i:s') ?? '—' }}</td>
                        <td>{{ $record?->checked_out_at ? number_format($record->workedSeconds() / 3600, 2).' h' : '—' }}</td>
                        <td>
                            @if($record)
                                @php($workedSeconds = $record->workedSeconds())
                                <span class="work-timer" data-work-timer data-started-at="{{ $record->checked_in_at->timestamp }}" data-breaks="{{ $record->breaks->map(fn ($break) => [$break->break_started_at->timestamp, $break->break_ended_at?->timestamp])->values()->toJson() }}" @if($record->checked_out_at) data-stopped-at="{{ $record->checked_out_at->timestamp }}" @endif>{{ sprintf('%02d:%02d:%02d', intdiv($workedSeconds, 3600), intdiv($workedSeconds % 3600, 60), $workedSeconds % 60) }}</span>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                        <td>{{ $employee->daily_working_hours }} h</td>
                        <td>
                            @if($date === today()->toDateString() && ! $record?->checked_out_at)
                                @if($record)
                                    @if($openBreak)
                                        <form method="POST" action="{{ route('attendance.break-out', $employee) }}">@csrf<button aria-label="Break out {{ $employee->name }}">Break out</button></form>
                                    @else
                                        <form method="POST" action="{{ route('attendance.break-in', $employee) }}">@csrf<button aria-label="Break in {{ $employee->name }}">Break in</button></form>
                                        <form method="POST" action="{{ route('attendance.check-out', $employee) }}">@csrf<button aria-label="Check out {{ $employee->name }}">Check out</button></form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('attendance.check-in', $employee) }}">@csrf<button aria-label="Check in {{ $employee->name }}">Check in</button></form>
                                @endif
                                <small>Current time (IST): <span data-current-time data-timezone="Asia/Kolkata">{{ now('Asia/Kolkata')->format('H:i:s') }}</span></small>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">{{ $isAdmin ? 'No employees found. Add an employee to start tracking attendance.' : 'No employee profile linked yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $employees->links() }}</div>
            <p class="hint">Times use India Standard Time (IST). Check-in and check-out record the current time. Past dates are read-only.</p>
        </section>
    </div>
    @include('partials.holiday-calendar')
    <script>
        const serverTimestampAtLoad = {{ now()->timestamp }};
        const browserTimestampAtLoad = Date.now();

        const updateAttendanceClocks = () => {
            const currentTimestamp = serverTimestampAtLoad + Math.floor((Date.now() - browserTimestampAtLoad) / 1000);

            document.querySelectorAll('[data-work-timer]').forEach((timer) => {
                const startedAt = Number(timer.dataset.startedAt);
                const stoppedAt = timer.dataset.stoppedAt
                    ? Number(timer.dataset.stoppedAt)
                    : currentTimestamp;
                const breaks = JSON.parse(timer.dataset.breaks || '[]');
                const breakSeconds = breaks.reduce((total, [breakStartedAt, breakEndedAt]) => {
                    return total + Math.max(0, Math.min(stoppedAt, breakEndedAt || currentTimestamp) - breakStartedAt);
                }, 0);
                const elapsedSeconds = Math.max(0, Math.floor(stoppedAt - startedAt - breakSeconds));
                const hours = Math.floor(elapsedSeconds / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((elapsedSeconds % 3600) / 60).toString().padStart(2, '0');
                const seconds = (elapsedSeconds % 60).toString().padStart(2, '0');

                timer.textContent = `${hours}:${minutes}:${seconds}`;
            });

            document.querySelectorAll('[data-current-time]').forEach((clock) => {
                clock.textContent = new Intl.DateTimeFormat('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hourCycle: 'h23',
                    timeZone: clock.dataset.timezone,
                }).format(new Date(currentTimestamp * 1000));
            });
        };

        updateAttendanceClocks();
        window.setInterval(updateAttendanceClocks, 1000);
    </script>
@endsection
