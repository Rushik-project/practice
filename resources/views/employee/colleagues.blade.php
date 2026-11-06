@extends('layouts.attendance')

@section('content')
    <h1>Colleagues</h1>
    <p>Meet the people on your team.</p>

    <section class="panel" aria-label="Colleague directory">
        @if($colleagues->isEmpty())
            <div class="empty">No colleagues to show yet.</div>
        @else
            <div class="colleague-grid">
                @foreach($colleagues as $colleague)
                    <article class="colleague-card">
                        @if($photoUrls[$colleague->id])
                            <img class="colleague-photo" src="{{ $photoUrls[$colleague->id] }}" alt="{{ $colleague->name }} profile photo">
                        @else
                            <div class="colleague-photo-placeholder" aria-label="No profile photo uploaded">{{ strtoupper(substr($colleague->name, 0, 1)) }}</div>
                        @endif
                        <div class="colleague-info">
                            <strong>{{ $colleague->name }}</strong>
                            <span>{{ $colleague->department }}</span>
                            <span><a href="mailto:{{ $colleague->email }}">{{ $colleague->email }}</a></span>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="pagination">{{ $colleagues->links() }}</div>
        @endif
    </section>
@endsection