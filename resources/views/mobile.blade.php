<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
        <meta name="referrer" content="no-referrer">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#0f172a">
        <meta name="color-scheme" content="light dark">

        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Family Hub">

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="icon" href="/favicon.ico">

        <title>Family Hub</title>

        @vite(['resources/css/app.css', 'resources/js/mobile.js'])
    </head>
    <body class="antialiased font-sans">
        <div id="app"></div>
    </body>
</html>
