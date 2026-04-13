<?php

use Illuminate\Support\Facades\Route;
use App\Models\FinancialAccount;
use App\Models\FinanceLog;

Route::get('/', function () {
    return view('welcome');
});

