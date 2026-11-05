<section class="panel holiday-calendar" aria-labelledby="calendar-title">
    <div class="calendar-heading">
        <div><h2 id="calendar-title">Calendar · {{ $calendar['month']->format('F Y') }}</h2><p>India national holidays and your company holidays.</p></div>
        <nav class="calendar-navigation" aria-label="Calendar month navigation">
            @if($calendar['previous'])<a href="{{ request()->fullUrlWithQuery(['month' => $calendar['previous'], 'page' => null]) }}" aria-label="Previous month">&larr; Previous</a>@endif
            <a href="{{ request()->fullUrlWithQuery(['month' => today()->format('Y-m'), 'page' => null]) }}">This month</a>
            @if($calendar['next'])<a href="{{ request()->fullUrlWithQuery(['month' => $calendar['next'], 'page' => null]) }}" aria-label="Next month">Next &rarr;</a>@endif
        </nav>
    </div>
    <form method="GET" action="{{ url()->current() }}" class="filters">
        @if(request('date'))<input type="hidden" name="date" value="{{ request('date') }}">@endif
        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
        <div><label for="calendar-month">Choose month</label><input type="month" id="calendar-month" name="month" value="{{ $calendar['month']->format('Y-m') }}" min="2000-01" max="2100-12" required></div>
        <button>Show month</button>
    </form>
    <div class="table-wrap">
        <table class="calendar-table" aria-label="Holiday calendar for {{ $calendar['month']->format('F Y') }}">
            <thead><tr>@foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $weekday)<th scope="col">{{ $weekday }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($calendar['weeks'] as $week)
                <tr>
                @foreach($week as $day)
                    <td class="calendar-day {{ ! $day['currentMonth'] ? 'calendar-outside' : '' }} {{ $day['date']->isToday() ? 'calendar-today' : '' }}">
                        <time datetime="{{ $day['date']->toDateString() }}" @if($day['date']->isToday()) aria-current="date" @endif>{{ $day['date']->day }}</time>
                        @foreach($day['events'] as $event)
                            <span class="calendar-event {{ $event['type'] === 'National' ? 'calendar-national' : 'calendar-company' }}">{{ $event['name'] }}<small>{{ $event['type'] }} holiday</small></span>
                        @endforeach
                    </td>
                @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="calendar-national-list">
        <h2>India national holidays · {{ $calendar['month']->year }}</h2>
        @foreach($calendar['nationalHolidays'] as $holiday)
            <span class="calendar-national-summary"><strong>{{ $holiday['name'] }}</strong> — {{ $holiday['date']->format('d M Y') }}</span>
        @endforeach
    </div>
    <p class="hint">Regional and festival holidays can be added by your administrator to the <a href="{{ route('holidays.index') }}">company holiday list</a>. <a href="https://www.ursc.gov.in/holidays.jsp" target="_blank" rel="noopener noreferrer">Government holiday reference</a></p>
</section>
