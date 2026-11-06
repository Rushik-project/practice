@extends('layouts.attendance')

@section('content')
    <h1>Monthly attendance summary</h1>
    <p>Present and absent working days for each employee. Working days are Monday–Saturday, excluding holidays.</p>

    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="panel">
        <form method="GET" action="{{ route('admin.attendance-summary') }}" class="filters">
            <div>
                <label for="month">Report month</label>
                <input id="month" type="month" name="month" value="{{ $month }}" min="2000-01" max="{{ today()->format('Y-m') }}" required>
            </div>
            <button>Show report</button>
            <a href="{{ route('admin.attendance-summary') }}">Current month</a>
        </form>

        <div class="stats">
            <div class="card"><span>Working days in period</span><strong>{{ $workingDays }}</strong></div>
            <div class="card"><span>Total present days</span><strong>{{ $present }}</strong></div>
            <div class="card"><span>Total absent days</span><strong>{{ $absent }}</strong></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Employee</th><th>Department</th><th>Working days</th><th>Present</th><th>Absent</th><th>Absent dates</th></tr>
                </thead>
                <tbody>
                    @forelse($summaries as $summary)
                        <tr>
                            <td><strong>{{ $summary['employee']->name }}</strong><small>{{ $summary['employee']->email }}</small></td>
                            <td>{{ $summary['employee']->department }}</td>
                            <td>{{ $summary['working_days'] }}</td>
                            <td><span class="badge present">{{ $summary['present'] }}</span></td>
                            <td><span class="badge">{{ $summary['absent'] }}</span></td>
                            <td>{{ $summary['absent_dates'] ? implode(', ', $summary['absent_dates']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $employees->links() }}</div>
        <p class="hint">The current month includes working days only through today. Employees are counted from their profile creation date. Attendance on weekends and holidays is not included in the totals.</p>
    </section>
@endsection
