@extends('layouts.attendance')
@section('content')
    <h1>{{ $isAdmin ? 'Employee leave requests' : 'My leave requests' }}</h1>
    <p>{{ $isAdmin ? 'Review pending requests and approve or reject leave for your employees.' : 'Apply for leave and track your administrator’s decision.' }}</p>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="{{ ! $isAdmin && $employee ? 'layout' : '' }}">
        <section class="panel">
            <h2>{{ $isAdmin ? 'Requests awaiting review and history' : 'Request history' }}</h2>
            <div class="table-wrap"><table>
                <thead><tr>@if($isAdmin)<th>Employee</th>@endif<th>Dates</th><th>Reason</th><th>Status</th><th>{{ $isAdmin ? 'Action' : 'Reviewed' }}</th></tr></thead>
                <tbody>
                @forelse($leaves as $leave)
                    <tr>
                        @if($isAdmin)<td><strong>{{ $leave->employee->name }}</strong><small>{{ $leave->employee->department }}</small></td>@endif
                        <td>{{ $leave->start_date->format('d M Y') }}<small>to {{ $leave->end_date->format('d M Y') }}</small></td>
                        <td style="white-space:pre-wrap;min-width:180px;max-width:360px;overflow-wrap:anywhere">{{ $leave->reason }}</td>
                        <td><span class="badge {{ $leave->status === 'approved' ? 'present' : ($leave->status === 'rejected' ? 'error' : '') }}">{{ ucfirst($leave->status) }}</span></td>
                        <td>
                            @if($isAdmin && $leave->status === 'pending')
                                <form method="POST" action="{{ route('leave.review', $leave) }}" style="display:flex;gap:8px">
                                    @csrf @method('PATCH')
                                    <button name="status" value="approved" aria-label="Approve leave for {{ $leave->employee->name }}">Approve</button>
                                    <button name="status" value="rejected" style="background:#963e37" aria-label="Reject leave for {{ $leave->employee->name }}">Reject</button>
                                </form>
                            @else
                                {{ $leave->reviewed_at?->format('d M Y H:i') ?? 'Awaiting review' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No leave requests yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $leaves->links() }}</div>
        </section>
        @if(! $isAdmin)
            @if($employee)
                <aside class="panel">
                    <h2>Apply for leave</h2>
                    <form method="POST" action="{{ route('leave.store') }}">
                        @csrf
                        <div class="field"><label for="start_date">Start date</label><input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" min="{{ today()->toDateString() }}" required></div>
                        <div class="field"><label for="end_date">End date</label><input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" min="{{ today()->toDateString() }}" required></div>
                        <div class="field"><label for="reason">Reason for leave</label><textarea id="reason" name="reason" rows="5" maxlength="2000" required style="width:100%;font:inherit;padding:10px;border:1px solid #ccd9d2;border-radius:8px;resize:vertical">{{ old('reason') }}</textarea></div>
                        <button>Submit leave request</button>
                    </form>
                    <p class="hint">Both dates are included. Your request stays pending until your administrator approves or rejects it.</p>
                </aside>
            @else
                <div class="notice" style="margin-top:20px">Your account is not linked to an employee profile. Contact your administrator before applying for leave.</div>
            @endif
        @endif
    </div>
@endsection
