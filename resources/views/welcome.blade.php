<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Family Hub</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if(app()->environment('production') || env('HIDE_CURSOR', false))
        <style>
            * {
                cursor: none !important;
            }
        </style>
        @endif
    </head>
    <body class="antialiased font-sans">
        <div id="app"></div>
    </body>
</html>
