<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class MultiGuardAuthenticate extends FilamentAuthenticate
{
    /**
     * Handle an incoming request.
     * Check both 'web' and 'customer' guards before redirecting to login.
     * Dynamically switch the panel's auth guard so Filament recognizes the user
     * (profile menu, logout, user name in header, etc.).
     */
    protected function authenticate($request, array $guards): void
    {
        // Admin takes priority
        if (auth('web')->check()) {
            auth()->shouldUse('web');
            Filament::getCurrentOrDefaultPanel()->authGuard('web');
            return;
        }

        // Customer guard
        if (auth('customer')->check()) {
            auth()->shouldUse('customer');
            Filament::getCurrentOrDefaultPanel()->authGuard('customer');
            return;
        }

        // Neither guard is authenticated — redirect to login
        $this->unauthenticated($request, $guards);
    }
}
