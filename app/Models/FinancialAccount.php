<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = [
        'name',
        'account_number',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function financeLogs(): HasMany
    {
        return $this->hasMany(FinanceLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
