@extends('layouts.attendance')

@section('content')
    <h1>My monthly summary</h1>
    <p>Review your attendance and worked hours for the selected month.</p>

    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="panel">
        <form method="GET" action="{{ route('employee.attendance-summary') }}" class="filters">
            <div>
                <label for="month">Report month</label>
                <input id="month" type="month" name="month" value="{{ $month }}" min="2000-01" max="{{ today()->format('Y-m') }}" required>
            </div>
            <button>Show summary</button>
            <a href="{{ route('employee.attendance-summary') }}">Current month</a>
        </form>

        @if($employee)
            <p><strong>{{ $employee->name }}</strong> · {{ $employee->department }}</p>
        @else
            <div class="notice">Your account is not linked to an employee record. Ask your administrator to link your profile to view your attendance summary.</div>
        @endif

        <div class="stats">
            <div class="card"><span>Working days</span><strong>{{ $summary['working_days'] }}</strong></div>
            <div class="card"><span>Days present</span><strong>{{ $summary['present'] }}</strong></div>
            <div class="card"><span>Days absent</span><strong>{{ $summary['absent'] }}</strong></div>
            <div class="card"><span>Total worked hours</span><strong>{{ number_format($summary['worked_seconds'] / 3600, 2) }} h</strong></div>
        </div>

        <p class="hint">Working days are Monday–Saturday, excluding national and company holidays. The current month includes dates only through today. Worked hours exclude break time.</p>
    </section>
@endsection
