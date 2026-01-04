<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-neutral-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                @php
                    $logoHref = request()->routeIs('password.confirm') || request()->routeIs('password.confirm.*')
                        ? route('dashboard')
                        : route('home');
                @endphp
                <a href="{{ $logoHref }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <img src="{{ asset('AF-Logo-Sponsor-1.png') }}" alt="{{ config('app.name', 'Adira') }}" width="162" height="108" class="mx-auto" />

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
