<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Token-protected loan creation — used by the chipkie.com standalone chat page
Route::post('/loans/create', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token || $token !== config('services.loan_api_token')) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    return app(App\Http\Controllers\LoanChatController::class)->store($request);
});
