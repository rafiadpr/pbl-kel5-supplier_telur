<?php

namespace App\Filament\Traits;

/**
 * Helper trait providing customer/admin guard detection methods.
 * Use in Filament Resources, Widgets, and Pages.
 */
trait InteractsWithCustomerAuth
{
    /**
     * Check if the currently authenticated user is a Customer.
     */
    public static function isCustomer(): bool
    {
        return auth('customer')->check();
    }

    /**
     * Check if the currently authenticated user is an Admin.
     */
    public static function isAdmin(): bool
    {
        return auth('web')->check();
    }
}
