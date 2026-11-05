<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f4f5f7">
        <title>Create your account · {{ config('app.name', 'Northstar') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.theme-head')
    </head>
    <body class="auth-page">
        <div class="theme-toolbar">@include('partials.theme-toggle')</div>
        <main class="register-layout">
            <section class="register-story" aria-labelledby="story-title">
                <a class="brand" href="{{ url('/') }}" aria-label="{{ config('app.name', 'Northstar') }} home">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M16 3.5 18.7 13.3 28.5 16l-9.8 2.7L16 28.5l-2.7-9.8L3.5 16l9.8-2.7L16 3.5Z" fill="currentColor"/>
                            <circle cx="16" cy="16" r="3" fill="#17243A"/>
                        </svg>
                    </span>
                    <span>{{ config('app.name', 'Northstar') }}</span>
                </a>

                <div class="story-copy">
                    <span class="eyebrow"><span class="eyebrow-dot"></span> A good place to begin</span>
                    <h1 id="story-title">Make room for<br>what’s next.</h1>
                    <p>Bring your ideas together and turn the things you care about into meaningful progress.</p>
                </div>

                <div class="story-card" aria-hidden="true">
                    <div class="story-card-top">
                        <span class="story-card-label">YOUR NEXT CHAPTER</span>
                        <span class="story-card-icon">✳</span>
                    </div>
                    <div class="story-card-line story-card-line-long"></div>
                    <div class="story-card-line story-card-line-short"></div>
                    <div class="story-card-progress"><span></span></div>
                    <div class="story-card-footer"><span>A little progress, every day.</span><span>01 / 03</span></div>
                </div>

                <div class="story-footer">
                    <span class="footer-spark" aria-hidden="true">✦</span>
                    <span>Start simple. Build something great.</span>
                </div>
            </section>

            <section class="register-content" aria-labelledby="register-title">
                <div class="register-form-wrap">
                    <div class="mobile-brand">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M16 3.5 18.7 13.3 28.5 16l-9.8 2.7L16 28.5l-2.7-9.8L3.5 16l9.8-2.7L16 3.5Z" fill="currentColor"/>
                                <circle cx="16" cy="16" r="3" fill="#17243A"/>
                            </svg>
                        </span>
                        <span>{{ config('app.name', 'Northstar') }}</span>
                    </div>

                    <div class="form-heading">
                        <span class="form-kicker">GET STARTED — IT’S FREE</span>
                        <h2 id="register-title">Create your account</h2>
                        <p>A few details and you’re on your way.</p>
                    </div>

                    @if (session('status'))
                        <div class="form-success" role="status">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="form-alert" role="alert">
                            Please check the highlighted fields and try again.
                        </div>
                    @endif

                    <form class="register-form" method="POST" action="{{ route('register.store') }}">
                        @csrf

                        <div class="field">
                            <label for="name">Your name</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                autocomplete="name"
                                placeholder="e.g. Alex Morgan"
                                required
                                autofocus
                                @class(['field-input', 'field-input-error' => $errors->has('name')])
                                @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif
                            >
                            @error('name')
                                <p class="field-error" id="name-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="email">Email address</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                placeholder="you@example.com"
                                required
                                @class(['field-input', 'field-input-error' => $errors->has('email')])
                                @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif
                            >
                            @error('email')
                                <p class="field-error" id="email-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="At least 8 characters"
                                required
                                @class(['field-input', 'field-input-error' => $errors->has('password')])
                                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-hint password-error" @endif
                            >
                            <p class="field-hint" id="password-hint">Use at least 8 characters.</p>
                            @error('password')
                                <p class="field-error" id="password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Confirm password</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Enter your password again"
                                required
                                @class(['field-input', 'field-input-error' => $errors->has('password')])
                                @if ($errors->has('password')) aria-invalid="true" aria-describedby="confirmation-error" @endif
                            >
                            @if ($errors->has('password'))
                                <p class="field-error" id="confirmation-error">{{ $errors->first('password') }}</p>
                            @endif
                        </div>

                        <button class="register-button" type="submit">
                            <span>Create account</span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                <path d="M4.25 10h11.5m-4.5-4.5 4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>

                    <p class="form-legal">By creating an account, you agree to use this service responsibly.</p>
                    <p class="form-legal">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
                </div>

                <footer class="content-footer">
                    <span>Thoughtfully made for your next big thing.</span>
                    <span class="footer-heart" aria-hidden="true">♥</span>
                </footer>
            </section>
        </main>
    </body>
</html>
