<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var choices = ['light', 'dark-teal', 'slate-night', 'coffee-night'];
            var choice = 'light';
            try {
                choice = localStorage.getItem('jooal-theme-choice') || localStorage.getItem('jooal-theme-palette') || (localStorage.getItem('jooal-theme') === 'dark' ? 'dark-teal' : 'light');
            } catch (error) {}
            if (!choices.includes(choice)) choice = 'light';
            document.documentElement.dataset.theme = choice === 'light' ? 'light' : 'dark';
            document.documentElement.dataset.themePalette = choice;
        }());
    </script>
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-brand" aria-label="JOOAL POS">
                <span class="auth-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 13.5 16 5l10 8.5v12a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 6 25.5z"/><path d="M12 27v-8h8v8M11 13h10"/></svg>
                </span>
                <span class="auth-brand-name">JOOAL <span>POS</span></span>
            </div>

            <header class="auth-heading">
                <p class="auth-eyebrow">Sistem kasir multi-cabang</p>
                <h1 id="login-title">Selamat datang di JOOAL POS</h1>
                <p>Masuk untuk melanjutkan pekerjaan operasional Anda.</p>
            </header>

            @if ($errors->any())
                <div class="auth-error" role="alert" aria-live="polite">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <label for="login-email">Email
                    <input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="pos-input">
                </label>
                <label for="login-password">Kata sandi
                    <span class="auth-password-control">
                        <input id="login-password" type="password" name="password" required autocomplete="current-password" class="pos-input" data-password-input>
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-controls="login-password" aria-label="Tampilkan kata sandi" aria-pressed="false">Tampilkan</button>
                    </span>
                </label>
                <button type="submit" class="pos-button auth-submit bg-emerald-700 text-white hover:bg-emerald-800">Masuk</button>
            </form>
        </section>
    </main>
    <script>
        (function () {
            var toggle = document.querySelector('[data-password-toggle]');
            var input = document.querySelector('[data-password-input]');
            if (!toggle || !input) return;
            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
                toggle.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
                toggle.setAttribute('aria-pressed', String(show));
            });
        }());
    </script>
</body>
</html>
