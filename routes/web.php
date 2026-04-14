<?php

use App\Http\Controllers\LoanChatAIController;
use App\Http\Controllers\LoanChatController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true]));

// Chat-based loan creation
Route::get('/loans/chat',         [LoanChatAIController::class, 'show'])->name('loans.chat');
Route::post('/loans/chat/message',[LoanChatAIController::class, 'message'])->name('loans.chat.message');
Route::post('/loans/chat',        [LoanChatController::class,   'store'])->name('loans.chat.store');
