@extends('layouts.attendance')
@section('content')
    <h1>Holiday list</h1>
    <p>Company and India public holidays for {{ $year }}.</p>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(! $hasProfile)<div class="notice">Contact your administrator to link your employee profile and view your company holidays.</div>@endif
    <div class="{{ $isAdmin ? 'layout' : '' }}">
        <section class="panel">
            <form method="GET" action="{{ route('holidays.index') }}" class="filters">
                <div><label for="year">Year</label><input type="number" id="year" name="year" min="2000" max="2100" value="{{ $year }}" required></div>
                <button>Show holidays</button>
            </form>
            <h2>India public holidays</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Holiday</th><th>Date</th><th>Day</th></tr></thead>
                <tbody>
                @foreach($nationalHolidays as $holiday)
                    <tr><td style="white-space:normal;overflow-wrap:anywhere">{{ $holiday['name'] }}</td><td>{{ $holiday['date']->format('d M Y') }}</td><td>{{ $holiday['date']->format('l') }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
            <h2>Company holidays</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Holiday</th><th>Date</th><th>Day</th></tr></thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr><td style="white-space:normal;overflow-wrap:anywhere">{{ $holiday->name }}</td><td>{{ $holiday->date->format('d M Y') }}</td><td>{{ $holiday->date->format('l') }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">No holidays have been added for {{ $year }}.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="pagination">{{ $holidays->links() }}</div>
        </section>
        @if($isAdmin)
            <aside class="panel">
                <h2>Add holiday</h2>
                <form method="POST" action="{{ route('holidays.store') }}">
                    @csrf
                    <div class="field"><label for="name">Holiday name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
                    <div class="field"><label for="date">Date</label><input id="date" name="date" type="date" value="{{ old('date') }}" min="2000-01-01" max="2100-12-31" required></div>
                    <button>Add holiday</button>
                </form>
                <p class="hint">Holidays are visible to your employees. Add the dates observed by your company.</p>
            </aside>
        @endif
    </div>
@endsection
