<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | D'fitness Gym</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('dfitness-theme') === 'dark' ? 'dark' : 'light'; } catch (error) { document.documentElement.dataset.theme = 'light'; }</script>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-bold-rounded/css/uicons-bold-rounded.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=30">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('login') }}"><img class="brand-logo" src="{{ asset('images/dfitness-logo.png') }}" alt="D'fitness Gym logo" width="46" height="46"><span>D'fitness <strong>Gym</strong></span></a>
        <nav class="header-actions" aria-label="Main navigation">
            <a class="header-link" href="{{ auth()->check() ? route('dashboard') : route('login') }}"><i class="fi fi-br-house-chimney" aria-hidden="true"></i><span>Home</span></a>
            <button id="theme-toggle" class="theme-toggle" type="button" aria-pressed="false"><i id="theme-toggle-icon" class="fi fi-br-moon" aria-hidden="true"></i><span id="theme-toggle-label">Dark mode</span></button>
            @auth<form method="POST" action="{{ route('logout') }}">@csrf<button class="header-link header-logout" type="submit"><i class="fi fi-br-sign-out-alt" aria-hidden="true"></i><span>Logout</span></button></form>@endauth
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="site-footer">D'fitness Gym <span>·</span> Stronger together <span>·</span> Uicons by <a href="https://www.flaticon.com/uicons" target="_blank" rel="noopener noreferrer">Flaticon</a></footer>
    <script src="{{ asset('js/password-toggle.js') }}?v=1"></script>
    <script src="{{ asset('js/theme-toggle.js') }}?v=2"></script>
    @yield('scripts')
</body>
</html>
