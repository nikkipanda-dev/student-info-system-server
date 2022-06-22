<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get("/test", [AuthController::class, "test"]);

Route::post("/login", [AuthController::class, "authenticate"]);

Route::middleware(['auth:sanctum'])->group(function () {
    // Admin
    Route::post("/logout", [AuthController::class, "adminLogout"]);
    Route::get("/admins", [AccountController::class, "adminGetAll"]);
    Route::post("/admin-register", [AccountController::class, "adminStore"]);
    Route::post("/toggle-admin-status", [AccountController::class, "adminToggleStatus"]);
});