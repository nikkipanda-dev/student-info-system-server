<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\StudentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\RecordTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use RecordTrait, ResponseTrait;

    public function studentPaymentGetAll(Request $request) {
        Log::info("Entering PaymentController studentPaymentGetAll...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
        ]);

        try {
            $student = $this->getRecord("students", $request->slug);

            if (!($student)) {
                Log::notice("Student does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            $payments = StudentPayment::with('studentFiles')->where('student_id', $student->id)->get();

            if (count($payments) === 0) {
                Log::notice("No payments yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            Log::info("Successfully retrieved student's payments. Leaving PaymentController studentPaymentGetAll...\n");
            return $this->successResponse("details", $payments);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student's payments. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPaymentsStore(Request $request) {
        Log::info("Entering AccountController studentPaymentsStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'mode_of_payment' => 'bail|required|in:bank_transfer_bdo,bank_transfer_security_bank,cash,gcash',
            'payment_type' => 'bail|required|in:full,installment',
            'date_paid' => 'bail|required|date_format:Y-m-d|before_or_equal:'.now(),
            'amount_paid' => 'bail|required|numeric|min:2|max:100000',
            'balance' => 'bail|nullable|numeric|min:2|max:100000',
            'course' => 'bail|required|in:bsit,bscs,bsis,bsba',
            'year' => 'bail|required|regex:/^\d{4}$/',
            'term' => 'bail|required|numeric|in:1,2,3',
            'payments' => 'bail|required|array|min:1',
            'status' => 'bail|required|in:pending,verified',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
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

                    if ($payment->isValid()) {
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

            Log::info("Successfully stored student ID " . $student->id . "'s payments. Leaving AccountController studentPaymentsStore...\n");
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

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
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

            $payment = $this->getRecord("student-payments", $request->slug);

            $payment->status = $request->status;

            $payment->save();

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

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->student_slug);

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
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

            $payment = $this->getRecord("student-payments", $request->slug);

            if (!($payment)) {
                Log::error("Payment does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalPayment = $payment->getOriginal();
            $payment->delete();

            if (StudentPayment::find($originalPayment['id'])) {
                Log::error("Failed to soft delete student's payment.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully soft deleted student ID " . $student->id . "'s payment. Leaving PaymentController studentPaymentDestroy...\n");
            return $this->successResponse("details", [
                'slug' => $originalPayment['slug'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to soft delete student's payment. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
