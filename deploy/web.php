<?php

use App\Http\Controllers\LoanChatAIController;
use App\Http\Controllers\LoanChatController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true]));

// Chat-based loan creation (main route)
Route::get('/loans/chat',         [LoanChatAIController::class, 'show'])->name('loans.chat');
Route::post('/loans/chat/message',[LoanChatAIController::class, 'message'])->name('loans.chat.message');
Route::post('/loans/chat',        [LoanChatController::class,   'store'])->name('loans.chat.store');

// Hidden beta route — same flow, obscure URL for live testing on chipkie.com
// POST requests go to the same /loans/chat/* endpoints (hardcoded in LoanChat.vue)
Route::get('/apply-beta-9x4k2m', [LoanChatAIController::class, 'show']);
