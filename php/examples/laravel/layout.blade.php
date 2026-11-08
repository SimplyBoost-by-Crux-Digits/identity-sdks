{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}

    {{-- Signed for the logged-in user, anonymous for guests. Either: --}}
    <x-simplyboost-widget />
    {{-- or: @simplyboostWidget --}}
</body>
</html>
