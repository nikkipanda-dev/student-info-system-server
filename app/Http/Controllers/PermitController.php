<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\StudentFile;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PermitController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function studentPermitGetAll(Request $request) {
        Log::info("Entering PermitController studentPermitGetAll...\n");

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

            $permits = StudentFile::where('student_id', $student->id)
                                  ->where('type', "permit")
                                  ->get();

            if (count($permits) === 0) {
                Log::notice("No permits yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            $ctr = 0;
            foreach ($permits as $permit) {
                $files = [];

                ++$ctr;

                $keys = [
                    'id',
                    'disk',
                    'extension',
                    'description',
                    'student_payment_id',
                    'student_registrar_file_id',
                    'updated_at',
                    'deleted_at',
                    'administrator_id',
                    'student_id',
                    'student_files'
                ];

                $files[] = [
                    'id' => $ctr,
                    'path' => Storage::disk($permit->disk)->url($permit->path) ?? '',
                    'slug' => $permit->slug,
                ];

                $permit['file'] = $files;
                $permit = $this->unsetFromArray($permit, $keys);
                $formattedArr[] = $permit;
            }

            Log::info("Successfully retrieved student's permits. Leaving PermitController studentPermitGetAll...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student's permits. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPermitStore(Request $request) {
        Log::info("Entering PermitController studentPermitStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'type' => 'bail|required|in:prelim,midterm,final',
            'image' => 'bail|required|image',
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

            if (!($user->is_admin)) {
                Log::error("User is not flagged as an admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            if (!($request->hasFile('image'))) {
                Log::error("Permit image does not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($request->image->isValid())) {
                Log::error("Failed to store student ID " . $student->id . "'s permit. File is invalid.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $filename = $this->generateSlug("student-files");
            $disk = "digital_ocean";

            $path = $request->image->storePubliclyAs(
                'permits',
                $filename . "." . $request->image->extension(),
                $disk,
            );

            if (!($this->getFile($disk, $path))) {
                Log::error("Failed to store student ID " . $student->id . "'s permit. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $permit = new StudentFile();

            $permit->administrator_id = $user->id;
            $permit->student_id = $student->id;
            $permit->disk = $disk;
            $permit->type = "permit";
            $permit->description = $request->type;
            $permit->path = $path;
            $permit->extension = $request->image->extension();
            $permit->year = $student->year;
            $permit->course = $student->course;
            $permit->term = $student->term;
            $permit->slug = $filename;

            $permit->save();

            if (!($permit)) {
                Log::error("Failed to store student's permit.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $permit['path'] = Storage::disk($permit->disk)->url($permit->path) ?? '';

            Log::info("Successfully stored student ID " . $student->id . "'s permit. Leaving PermitController studentPermitStore...\n");
            return $this->successResponse("details", $permit);
        } catch (\Exception $e) {
            Log::error("Failed to store student's permit. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPermitUpdate(Request $request) {
        Log::info("Entering PermitController studentPermitUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_files',
            'type' => 'bail|required|in:prelim,midterm,final',
            'image' => 'bail|required|image',
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

            if (!($user->is_admin)) {
                Log::error("User is not flagged as an admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            if (!($request->hasFile('image'))) {
                Log::error("Permit image does not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($request->image->isValid())) {
                Log::error("Failed to update student ID " . $student->id . "'s permit. File is invalid.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $permit = $this->getRecord("student-files", $request->slug);
            $disk = "digital_ocean";

            if (!($this->getFile($disk, $permit->path))) {
                Log::error("Failed to update student ID " . $student->id . "'s permit. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalRecord = $permit->getOriginal();
            $permit->delete();

            // Soft delete existing file
            if (StudentFile::find($originalRecord['id'])) {
                Log::error("Failed to update student ID " . $student->id . "'s permit. File was not soft deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            // Set existing file to private
            Storage::disk($originalRecord['disk'])->setVisibility($originalRecord['path'], 'private');

            $filename = $this->generateSlug("student-files");

            $path = $request->image->storePubliclyAs(
                'permits',
                $filename . "." . $request->image->extension(),
                $disk,
            );

            if (!($this->getFile($disk, $path))) {
                Log::error("Failed to update student ID " . $student->id . "'s permit. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $permit = new StudentFile();

            $permit->administrator_id = $user->id;
            $permit->student_id = $student->id;
            $permit->disk = $disk;
            $permit->type = $originalRecord['type'];
            $permit->description = $request->type;
            $permit->path = $path;
            $permit->extension = $request->image->extension();
            $permit->year = $originalRecord['year'];
            $permit->course = $originalRecord['course'];
            $permit->term = $originalRecord['term'];
            $permit->slug = $filename;

            $permit->save();

            $files[] = [
                'id' => 1,
                'path' => Storage::disk($permit->disk)->url($permit->path) ?? '',
                'slug' => $permit->slug,
            ];

            $keys = [
                'id',
                'disk',
                'extension',
                'description',
                'student_payment_id',
                'student_registrar_file_id',
                'updated_at',
                'deleted_at',
                'administrator_id',
                'student_id',
                'student_files',
            ];

            $permit['file'] = $files;
            $permit = $this->unsetFromArray($permit, $keys);

            Log::info("Successfully updated student ID " . $student->id . "'s permit. Leaving PermitController studentPermitUpdate...\n");
            return $this->successResponse("details", [
                'slug' => $permit['slug'],
                'file' => $permit['file'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update student's permit. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPermitDestroy(Request $request) {
        Log::info("Entering PermitController studentPermitDestroy...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_files',
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

            if (!($user->is_super_admin)) {
                Log::error("User is not flagged as a super admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $permit = $this->getRecord("student-files", $request->slug);
            $disk = "digital_ocean";

            if (!($this->getFile($disk, $permit->path))) {
                Log::error("Failed to soft delete student ID " . $student->id . "'s permit. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalRecord = $permit->getOriginal();
            $permit->delete();

            // Soft delete existing file
            if (StudentFile::find($originalRecord['id'])) {
                Log::error("Failed to soft delete student ID " . $student->id . "'s permit. File was not soft deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            // Set existing file to private
            Storage::disk($originalRecord['disk'])->setVisibility($originalRecord['path'], 'private');

            Log::info("Successfully soft deleted student ID " . $student->id . "'s permit. Leaving PermitController studentPermitDestroy...\n");
            return $this->successResponse("details", [
                'slug' => $originalRecord['slug'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to soft delete student's permit. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
