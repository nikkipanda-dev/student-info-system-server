<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermitController;
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

// File
Route::get("/student/file/download/{admin_slug}/{student_slug}/{file_slug}", [FileController::class, "studentFileDownload"]);

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

    // Student payments
    Route::get("/student-payments-get", [PaymentController::class, "studentPaymentGetAll"]);
    Route::post("/student-payment-store", [PaymentController::class, "studentPaymentStore"]);
    Route::post("/student-payment-update", [PaymentController::class, "studentPaymentUpdate"]);
    Route::post("/student-payment-destroy", [PaymentController::class, "studentPaymentDestroy"]);

    // Student certificate of registrations
    Route::get("/student-cors-get", [CorController::class, "studentCorGetAll"]);
    Route::post("/student-cor-store", [CorController::class, "studentCorStore"]);
    Route::post("/student-cor-update", [CorController::class, "studentCorUpdate"]);
    Route::post("/student-cor-destroy", [CorController::class, "studentCorDestroy"]);

    // Student permits
    Route::get("/student-permits-get", [PermitController::class, "studentPermitGetAll"]);
    Route::post("/student-permit-store", [PermitController::class, "studentPermitStore"]);
    Route::post("/student-permit-update", [PermitController::class, "studentPermitUpdate"]);
    Route::post("/student-permit-destroy", [PermitController::class, "studentPermitDestroy"]);
});