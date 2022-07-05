<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\StudentPayment;
use App\Models\UserLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;
use App\Traits\AdminTrait;

class DashboardController extends Controller
{
    use ResponseTrait, RecordTrait, AdminTrait;

    public function usersCountGet(Request $request) {
        Log::info("Entering DashboardController usersCountGet...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                $message = "Super administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'super administrator',
                ]));
            }

            if (!($user->is_super_admin)) {
                $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as a super admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $administrators = $this->getAllAdmins();
            $students = $this->getAllStudents();

            if (count($administrators) === 0) {
                Log::notice("No administrators yet.\n");
            }

            if (count($students) === 0) {
                Log::notice("No students yet.\n");
            }

            $formattedArr = [
                'administrators' => [
                    "count" => 0,
                ],
                'students' => [
                    "count" => 0,
                    "is_enrolled_count" => 0,
                    "is_dropped_count" => 0,
                    "is_expelled_count" => 0,
                    "is_graduate_count" => 0,
                    "by_year_level_count" => [
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                    ],
                    "by_course_count" => [
                        "bsit" => 0,
                        "bscs" => 0,
                        "bsis" => 0,
                        "bsba" => 0,
                    ],
                ],
            ];
            
            if (count($administrators) > 0) {
                foreach ($administrators as $admin) {
                    ++$formattedArr['administrators']['count'];
                }
            }

            if (count($students) > 0) {
                foreach ($students as $student) {
                    ++$formattedArr['students']['count'];

                    $enrollmentCategories = ["is_enrolled", "is_dropped", "is_expelled", "is_graduate"];
                    $courses = ["bsit", "bscs", "bsis", "bsba"];

                    for ($i = 0; $i < count($enrollmentCategories); $i++) {
                        ($student->{$enrollmentCategories[$i]}) && ++$formattedArr['students'][$enrollmentCategories[$i]."_count"];
                    }

                    for ($i = 0; $i <= 3; $i++) {
                        $ctr = $i + 1;
                        ($student->year === strval($ctr)) && ++$formattedArr['students']['by_year_level_count'][$ctr];
                    }

                    for ($i = 0; $i < count($courses); $i++) {
                        ($student->course === $courses[$i]) && ++$formattedArr['students']['by_course_count'][$courses[$i]];
                    }
                }
            }

            Log::info("Successfully retrieved student and administrators count. Leaving DashboardController usersCountGet...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student and administrators count. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function paymentsCountGet(Request $request) {
        Log::info("Entering DashboardController paymentsCountGet...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                $message = "Super administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'super administrator',
                ]));
            }

            if (!($user->is_super_admin)) {
                $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as a super admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $payments = StudentPayment::whereYear('created_at', date("Y"))->get();

            if (count($payments) === 0) {
                Log::notice("No payments for year " . date("Y") . " yet.\n");
            }

            $formattedArr = [
                'students' => [
                    "count" => 0,
                    "by_payment_type_count" => [
                        "full" => 0,
                        "installment" => 0,
                    ],
                    "by_mode_of_payment_count" => [
                        "bank_transfer_bdo" => 0,
                        "bank_transfer_security_bank" => 0,
                        "cash" => 0,
                        "gcash" => 0,
                    ],
                    "by_status_count" => [
                        "pending" => 0,
                        "verified" => 0,
                    ],
                    "by_amount_per_type_count" => [
                        "full" => 0,
                        "installment" => 0,
                    ],
                ],
            ];

            if (count($payments) > 0) {
                foreach ($payments as $payment) {
                    isset($payment->student_id) && ++$formattedArr['students']['count'];

                    $paymentTypes = ["full", "installment"];
                    $modeOfPayments = ["bank_transfer_bdo", "bank_transfer_security_bank", "cash", "gcash"];
                    $status = ["pending", "verified"];

                    for ($i = 0; $i < count($paymentTypes); $i++) {
                        ($payment->{"is_". $paymentTypes[$i]}) && ++$formattedArr['students']['by_payment_type_count'][$paymentTypes[$i]];
                    }

                    for ($i = 0; $i < count($modeOfPayments); $i++) {
                        ($payment->mode_of_payment === $modeOfPayments[$i]) && ++$formattedArr['students']['by_mode_of_payment_count'][$modeOfPayments[$i]];
                    }

                    for ($i = 0; $i < count($status); $i++) {
                        ($payment->status === $status[$i]) && ++$formattedArr['students']['by_status_count'][$status[$i]];
                    }

                    for ($i = 0; $i < count($paymentTypes); $i++) {
                        if ($payment->{"is_" . $paymentTypes[$i]}) {
                            if (isset($payment->amount_paid)) {
                                $formattedArr['students']['by_amount_per_type_count'][$paymentTypes[$i]] += $payment->amount_paid;
                            }

                            if (isset($payment->balance)) {
                                $formattedArr['students']['by_amount_per_type_count'][$paymentTypes[$i]] += $payment->balance;
                            }
                        }
                    }
                }
            }

            Log::info("Successfully retrieved payments overview. Leaving DashboardController paymentsCountGet...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve payments overview. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function recentActivitiesGet(Request $request) {
        Log::info("Entering DashboardController recentActivitiesGet...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                $message = "Super administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'super administrator',
                ]));
            }

            if (!($user->is_super_admin)) {
                $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as a super admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $userLogs = UserLog::latest()
                               ->whereYear('created_at', date("Y"))
                               ->limit(10)
                               ->get();

            if (count($userLogs) === 0) {
                Log::notice("No user logs for year ".date("Y")." yet.\n");
            }

            $formattedArr = [];

            if (count($userLogs) > 0) {
                $ctr = 0;

                foreach ($userLogs as $userLog) {
                    $formattedArr[] = [
                        "id" => ++$ctr,
                        "description" => $userLog->description,
                        "created_at" => $userLog->created_at,
                    ];
                }
            }

            Log::info("Successfully retrieved recent activities overview. Leaving DashboardController recentActivitiesGet...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve recent activities overview. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
