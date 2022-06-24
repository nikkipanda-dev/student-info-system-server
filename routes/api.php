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

    // Student
    Route::get("/students", [AccountController::class, "studentGetAll"]);
    Route::get("/student", [AccountController::class, "studentGet"]);
    Route::post("/student-register", [AccountController::class, "studentStore"]);
    Route::post("/student-name-update", [AccountController::class, "studentNameUpdate"]);
    Route::post("/student-display-photo-update", [AccountController::class, "studentDisplayPhotoUpdate"]);
    Route::post("/student-email-update", [AccountController::class, "studentEmailUpdate"]);
    Route::post("/student-password-update", [AccountController::class, "studentPasswordUpdate"]);
});