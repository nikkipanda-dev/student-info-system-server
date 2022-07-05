<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\RegistrarFileController;
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
Route::post("/student-authenticate", [AuthController::class, "authenticateStudent"]);

// File
Route::get("/student/file/download/{admin_slug}/{student_slug}/{file_slug}", [FileController::class, "studentFileDownload"]);
Route::get("/student/file/download/{student_slug}/{file_slug}", [FileController::class, "studentAuthFileDownload"]);

Route::middleware(['auth:sanctum'])->group(function () {
    // Admin
    Route::post("/logout", [AuthController::class, "adminLogout"]);
    Route::post("/student-logout", [AuthController::class, "studentLogout"]);
    Route::get("/admins", [AccountController::class, "adminGetAll"]);
    Route::post("/admin-register", [AccountController::class, "adminStore"]);
    Route::post("/toggle-admin-status", [AccountController::class, "adminToggleStatus"]);
    Route::post("/admin-name-update", [AccountController::class, "adminNameUpdate"]);
    Route::post("/admin-email-update", [AccountController::class, "adminEmailUpdate"]);
    Route::post("/admin-password-update", [AccountController::class, "adminPasswordUpdate"]);

    // Admin and student
    Route::post("/name-update", [AccountController::class, "nameUpdate"]);
    Route::post("/email-update", [AccountController::class, "emailUpdate"]);
    Route::post("/password-update", [AccountController::class, "passwordUpdate"]);

    // Dashboard
    Route::get("/users-count", [DashboardController::class, "usersCountGet"]);
    Route::get("/payments-count", [DashboardController::class, "paymentsCountGet"]);
    Route::get("/recent-activities-count", [DashboardController::class, "recentActivitiesGet"]);

    // Student
    Route::get("/students", [AccountController::class, "studentGetAll"]);
    Route::get("/student", [AccountController::class, "studentGet"]);
    Route::post("/student-register", [AccountController::class, "studentStore"]);
    Route::post("/student-enrollment-status-update", [AccountController::class, "studentEnrollmentStatusUpdate"]);
    Route::post("/student-name-update", [AccountController::class, "studentNameUpdate"]);
    Route::post("/student-course-update", [AccountController::class, "studentCourseUpdate"]);
    Route::post("/student-year-term-update", [AccountController::class, "studentYearTermUpdate"]);
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

    // Student registrar files
    Route::get("/student-registrar-files-get", [RegistrarFileController::class, "studentRegistrarFileGetAll"]);
    Route::post("/student-registrar-file-store", [RegistrarFileController::class, "studentRegistrarFileStore"]);
    Route::post("/student-registrar-file-update", [RegistrarFileController::class, "studentRegistrarFileUpdate"]);
    Route::post("/student-registrar-file-destroy", [RegistrarFileController::class, "studentRegistrarFileDestroy"]);

    // Authenticated student
    Route::get("/auth-student-payments-get", [PaymentController::class, "studentAuthPaymentGetAll"]);
    Route::get("/auth-student-cors-get", [CorController::class, "studentAuthCorGetAll"]);
    Route::get("/auth-student-permits-get", [PermitController::class, "studentAuthPermitGetAll"]);
    Route::get("/auth-student-registrar-files-get", [RegistrarFileController::class, "studentAuthRegistrarFileGetAll"]);

    // User logs
    Route::get("/user-logs", [LogController::class, "getUserLogs"]);
});