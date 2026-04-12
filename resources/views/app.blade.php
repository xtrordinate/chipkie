<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>Chipkie</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased bg-gray-50">
        @inertia
    </body>
</html>
