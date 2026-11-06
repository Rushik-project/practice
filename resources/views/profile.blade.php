@extends('layouts.attendance')

@section('content')
    <h1>Profile</h1>
    <p>View your account details and profile photo.</p>

    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="panel" aria-labelledby="profile-details-title">
        <h2 id="profile-details-title">Account details</h2>
        <div class="profile-photo-section">
            @if($photoUrl)
                <img class="profile-photo" src="{{ $photoUrl }}" alt="{{ $user->name }} profile photo">
            @else
                <div class="profile-photo-placeholder" aria-label="No profile photo uploaded">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
            @endif
            <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label for="profile_photo">Profile photo</label>
                    <input id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                    <small class="hint">Upload a JPG, PNG, or WebP image up to 2 MB.</small>
                </div>
                <button type="submit">Upload photo</button>
            </form>
        </div>
        <dl class="profile-details">
            <dt>Name</dt>
            <dd>{{ $user->name }}</dd>
            <dt>Email</dt>
            <dd>{{ $user->email }}</dd>
            <dt>Account type</dt>
            <dd>{{ $user->isAdmin() ? 'Administrator' : 'Employee' }}</dd>
        </dl>
    </section>
@endsection
