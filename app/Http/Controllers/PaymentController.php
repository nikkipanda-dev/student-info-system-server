<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\StudentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Traits\RecordTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use RecordTrait, ResponseTrait;

    // Authenticated admin
    public function studentPaymentGetAll(Request $request) {
        Log::info("Entering PaymentController studentPaymentGetAll...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
        ]);

        try {
            $authUser = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord("students", $request->slug);

            if (!($authUser)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            if (!($student)) {
                Log::notice("Student does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            if (!($authUser->is_admin)) {
                Log::error("User is not flagged as an admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $payments = StudentPayment::with('studentFiles')->where('student_id', $student->id)->get();

            if (count($payments) === 0) {
                Log::notice("No payments yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            foreach ($payments as $payment) {
                $files = [];

                $ctr = 0;
                foreach ($payment->studentFiles as $file) {
                    ++$ctr;

                    $files[] = [
                        'id' => $ctr,
                        'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                        'slug' => $file->slug,
                    ];
                }

                $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
                $payment = $this->unsetFromArray($payment, $keys);
                $payment['files'] = $files;
                $formattedArr[] = $payment;
            }

            Log::info("Successfully retrieved student's payments. Leaving PaymentController studentPaymentGetAll...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student's payments. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPaymentStore(Request $request) {
        Log::info("Entering AccountController studentPaymentStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'mode_of_payment' => 'bail|required|in:bank_transfer_bdo,bank_transfer_security_bank,cash,gcash',
            'payment_type' => 'bail|required|in:full,installment',
            'date_paid' => 'bail|required|date_format:Y-m-d|before_or_equal:'.now(),
            'amount_paid' => 'bail|required|numeric|min:2|max:100000',
            'balance' => 'bail|nullable|numeric|min:2|max:100000',
            'payments' => 'bail|required|array|min:1',
            'status' => 'bail|required|in:pending,verified',
        ]);

        $page = "/student";

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                $message = "Administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]));
            }

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($user->is_admin)) {
                $message = "User " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as an admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            if (!($request->hasFile('payments'))) {
                Log::error("Payment images do not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $filename = $this->generateSlug("student-payments");

            $newPayment = new StudentPayment();

            $newPayment->administrator_id = $user->id;
            $newPayment->student_id = $student->id;
            $newPayment->is_full = ($request->payment_type === "full") ? true : false;
            $newPayment->is_installment = ($request->payment_type === "installment") ? true : false;
            $newPayment->mode_of_payment = $request->mode_of_payment;
            $newPayment->date_paid = $request->date_paid;
            $newPayment->amount_paid = floatval($request->amount_paid);
            $newPayment->balance = $request->balance ?? null;
            $newPayment->course = $student->course;
            $newPayment->year = $student->year;
            $newPayment->term = $student->term;
            $newPayment->status = $request->status;
            $newPayment->slug = $filename;

            $newPayment->save();

            if (!($newPayment)) {
                Log::error("Failed to store student's payment details.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $transactionResponse = DB::transaction(function () use($request, $student, $user, $newPayment) {
                $disk = "digital_ocean";

                foreach ($request->payments as $payment) {
                    $isValid = false;
                    $errorText = null;
                    $array = [];

                    if (!($payment->isValid())) {
                        Log::error("Failed to store student ID " . $student->id . "'s payment. File is invalid.\n");
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        break;
                    }

                    $filename = $this->generateSlug("student-files");

                    $path = $payment->storePubliclyAs(
                        'payments',
                        $filename.".". $payment->extension(),
                        $disk,
                    );

                    if (!($this->getFile($disk, $path))) {
                        Log::error("Failed to store student ID " . $student->id . "'s payment. File was not saved to disk.\n");
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        break;
                    }

                    $file = new StudentFile();

                    $file->administrator_id = $user->id;
                    $file->student_id = $student->id;
                    $file->student_payment_id = $newPayment->id;
                    $file->disk = $disk;
                    $file->type = "payment";
                    $file->description = '';
                    $file->path = $path;
                    $file->extension = $payment->extension();
                    $file->course = $student->course;
                    $file->year = $student->year;
                    $file->term = $student->term;
                    $file->slug = $filename;

                    $file->save();

                    if (!($file)) {
                        Log::error("Failed to store student ID " . $student->id . "'s payment file details.\n");
                        $errorText = $this->errorResponse($this->getPredefinedResponse([
                            'type' => 'default',
                        ]));

                        break;
                    }

                    $array[] = $this->getFileUrl($disk, $path);
                    $isValid = true;
                }

                return [
                    "is_valid" => $isValid,
                    "error_text" => $errorText,
                ];
            }, 3);

            if (!($transactionResponse['is_valid'])) {
                Log::error("Failed to store student ID " . $student->id . "'s payments.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $ctr = 0;
            foreach ($newPayment->studentFiles as $file) {
                ++$ctr;

                $files[] = [
                    'id' => $ctr,
                    'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                    'slug' => $file->slug,
                ];
                $newPayment['files'] = $files;
            }

            $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
            $newPayment = $this->unsetFromArray($newPayment, $keys);

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " created a new payment for student number" . $student->student_number. ". New payment slug: " . $newPayment['slug']. ".\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully stored student ID " . $student->id . "'s payments. Leaving AccountController studentPaymentStore...\n");

            return $this->successResponse("details", $newPayment);
        } catch (\Exception $e) {
            Log::error("Failed to store student's payments. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPaymentUpdate(Request $request) {
        Log::info("Entering PaymentController studentPaymentUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_payments',
            'status' => 'bail|required|in:pending,verified',
        ]);

        $page = "/student";

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                $message = "Administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]));
            }

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($user->is_admin)) {
                $message = "User " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as an admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $payment = $this->getRecord("student-payments", $request->slug);

            $payment->status = $request->status;

            $payment->save();

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " updated a payment for student number" . $student->student_number . ". Payment slug: " . $payment->slug . ".\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully updated student ID " . $student->id . "'s payment. Leaving PaymentController studentPaymentUpdate...\n");

            return $this->successResponse("details", $payment->only(['status']));
        } catch (\Exception $e) {
            Log::error("Failed to update student's payment. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPaymentDestroy(Request $request) {
        Log::info("Entering PaymentController studentPaymentDestroy...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_payments',
        ]);

        $page = "/student";

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                $message = "Administrator does not exist on our system. Provided email " . $request->auth_email . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]));
            }

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($user->is_super_admin)) {
                $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " is not flagged as a super admin. ID: " . $user->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $payment = StudentPayment::with("studentFiles")->where('slug', $request->slug)->first();

            if (!($payment)) {
                Log::error("Payment does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $transactionResponse = DB::transaction(function () use($payment) {
                $isValid = null;
                $errorText = '';
                $originalPayment = $payment->getOriginal();
                $originalFile = '';

                // Soft delete each student's payment file
                foreach ($payment->studentFiles as $file) {
                    $isValid = false;
                    $errorText = '';

                    $originalFile = $file->getOriginal();
                    $file->delete();

                    if (StudentFile::find($originalFile['id'])) {
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);
                        
                        throw new Exception("Failed to soft delete student's file ID " . $originalFile['id'] . ". Student file still exists.\n");

                        break;
                    }

                    Storage::disk($originalFile['disk'])->setVisibility($originalFile['path'], 'private');

                    $isValid = true;
                    Log::info("Soft deleted student file ID " . $originalFile['id'] . ".\n");
                }

                if (!($isValid)) {
                    $errorText = $this->getPredefinedResponse([
                        'type' => 'default',
                    ]);

                    throw new Exception("Failed to soft delete student file ID " . $originalFile['id'] . ". Unable to soft delete one of the files.\n");
                } else {
                    // Soft delete payment if valid
                    $payment->delete();

                    if (StudentPayment::find($originalPayment['id'])) {
                        $isValid = false;
                        return $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        throw new Exception("Failed to soft delete student's payment ID ".$originalPayment['id'].".\n");
                    }

                    $isValid = true;
                    Log::info("Soft deleted student payment file ID " . $originalPayment['id'] . ".\n");
                }

                return [
                    "is_valid" => $isValid,
                    "error_text" => $errorText,
                    "payment" => $originalPayment,
                ];
            }, 3);

            if (!($transactionResponse['is_valid'])) {
                Log::error("Failed to soft delete student's payment.\n");
                return $this->errorResponse($transactionResponse['error_text']);
            }

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " deleted a payment from student number" . $student->student_number . ". Payment slug: " . $transactionResponse['payment']['slug'] . ".\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully soft deleted student ID " . $student->id . "'s payment. Leaving PaymentController studentPaymentDestroy...\n");

            return $this->successResponse("details", [
                'slug' => $transactionResponse['payment']['slug'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to soft delete student's payment. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    // Authenticated student
    public function studentAuthPaymentGetAll(Request $request) {
        Log::info("Entering PaymentController studentAuthPaymentGetAll...\n");

        $this->validate($request, [
            'slug' => 'bail|required|exists:students',
        ]);

        try {
            $student = $this->getRecord("students", $request->slug);

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            if (!($student->is_enrolled)) {
                Log::error("Student is not flagged as enrolled.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $payments = StudentPayment::with('studentFiles')
                                      ->where('student_id', $student->id)
                                      ->get();

            if (count($payments) === 0) {
                Log::notice("No payments yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            foreach ($payments as $payment) {
                $files = [];

                $ctr = 0;
                foreach ($payment->studentFiles as $file) {
                    ++$ctr;

                    $files[] = [
                        'id' => $ctr,
                        'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                        'slug' => $file->slug,
                    ];
                }

                $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
                $payment = $this->unsetFromArray($payment, $keys);
                $payment['files'] = $files;
                $formattedArr[] = $payment;
            }

            Log::info("Successfully retrieved authenticated student's payments. Leaving PaymentController studentAuthPaymentGetAll...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve authenticated student's payments. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
