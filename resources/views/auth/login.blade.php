<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f4f5f7">
        <title>Sign in · {{ config('app.name', 'Northstar') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-page">
        <main class="register-layout">
            <section class="register-story" aria-labelledby="story-title">
                <a class="brand" href="{{ route('login') }}" aria-label="{{ config('app.name', 'Northstar') }} home">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M16 3.5 18.7 13.3 28.5 16l-9.8 2.7L16 28.5l-2.7-9.8L3.5 16l9.8-2.7L16 3.5Z" fill="currentColor"/>
                            <circle cx="16" cy="16" r="3" fill="#17243A"/>
                        </svg>
                    </span>
                    <span>{{ config('app.name', 'Northstar') }}</span>
                </a>

                <div class="story-copy">
                    <span class="eyebrow"><span class="eyebrow-dot"></span> Good to see you again</span>
                    <h1 id="story-title">Pick up<br>where you left off.</h1>
                    <p>Your ideas and plans are right where you left them. Sign in to keep moving forward.</p>
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

            <section class="register-content" aria-labelledby="login-title">
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
                        <span class="form-kicker">WELCOME BACK</span>
                        <h2 id="login-title">Welcome back</h2>
                        <p>Sign in to pick up where you left off.</p>
                    </div>

                    @if ($errors->any())
                        <div class="form-alert" role="alert">
                            Your email or password is incorrect. Please try again.
                        </div>
                    @endif

                    <form class="register-form" method="POST" action="{{ route('login.store') }}">
                        @csrf

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
                                autofocus
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
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                required
                                @class(['field-input', 'field-input-error' => $errors->has('password')])
                                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif
                            >
                            @error('password')
                                <p class="field-error" id="password-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="remember-row" for="remember">
                            <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                            <span>Remember me</span>
                        </label>

                        <button class="register-button" type="submit">
                            <span>Sign in</span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                <path d="M4.25 10h11.5m-4.5-4.5 4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>

                    <p class="form-legal">New here? <a href="{{ route('register') }}">Create an account</a></p>
                </div>

                <footer class="content-footer">
                    <span>Thoughtfully made for your next big thing.</span>
                    <span class="footer-heart" aria-hidden="true">♥</span>
                </footer>
            </section>
        </main>
    </body>
</html>
