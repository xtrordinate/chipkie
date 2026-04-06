<?php

use App\Http\Controllers\LoanChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Chat-based loan creation (unauthenticated entry point)
Route::get('/loans/chat', [LoanChatController::class, 'show'])->name('loans.chat');
Route::post('/loans/chat', [LoanChatController::class, 'store'])->name('loans.chat.store');
