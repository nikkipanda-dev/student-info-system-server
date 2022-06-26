<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;

class CorController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function studentCorGetAll(Request $request) {
        Log::info("Entering CorController studentCorGetAll...\n");

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

            $cors = StudentFile::where('student_id', $student->id)
                                   ->where('type', "cor")
                                   ->get();

            if (count($cors) === 0) {
                Log::notice("No certificates of registration yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            foreach ($cors as $cor) {
                $cor['path'] = Storage::disk($cor->disk)->url($cor->path) ?? ''; 
            }

            Log::info("Successfully retrieved student's certificates of registration. Leaving CorController studentCorGetAll...\n");
            return $this->successResponse("details", $cors);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student's certificates of registration. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentCorStore(Request $request) {
        Log::info("Entering CorController studentCorStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
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
                Log::error("Certificate of registration image does not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($request->image->isValid())) {
                Log::error("Failed to store student ID " . $student->id . "'s certificate of registration. File is invalid.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $filename = $this->generateSlug("student-files");
            $disk = "digital_ocean";

            $path = $request->image->storePubliclyAs(
                'certificates_of_registration',
                $filename . "." . $request->image->extension(),
                $disk,
            );

            if (!($this->getFile($disk, $path))) {
                Log::error("Failed to store student ID " . $student->id . "'s certificate of registration. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $cor = new StudentFile();

            $cor->administrator_id = $user->id;
            $cor->student_id = $student->id;
            $cor->disk = $disk;
            $cor->type = "cor";
            $cor->description = '';
            $cor->path = $path;
            $cor->extension = $request->image->extension();
            $cor->year = $student->year;
            $cor->course = $student->course;
            $cor->term = $student->term;
            $cor->slug = $filename;

            $cor->save();

            if (!($cor)) {
                Log::error("Failed to store student's certificate of registration.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $cor['path'] = Storage::disk($cor->disk)->url($cor->path) ?? ''; 

            Log::info("Successfully stored student ID " . $student->id . "'s certificate of registration. Leaving CorController studentCorStore...\n");
            return $this->successResponse("details", $cor);
        } catch (\Exception $e) {
            Log::error("Failed to store student's certificate of registration. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentCorUpdate(Request $request) {
        Log::info("Entering CorController studentCorUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_files',
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
                Log::error("Certificate of registration image does not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($request->image->isValid())) {
                Log::error("Failed to store student ID " . $student->id . "'s certificate of registration. File is invalid.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $cor = $this->getRecord("student-files", $request->slug);
            $disk = "digital_ocean";

            if (!($this->getFile($disk, $cor->path))) {
                Log::error("Failed to update student ID " . $student->id . "'s certificate of registration. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalRecord = $cor->getOriginal();
            $cor->delete();

            // Soft delete existing file
            if (StudentFile::find($originalRecord['id'])) {
                Log::error("Failed to update student ID " . $student->id . "'s certificate of registration. File was not soft deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            // Set existing file to private
            Storage::disk($originalRecord['disk'])->setVisibility($originalRecord['path'], 'private');

            // Store new file
            $filename = $this->generateSlug("student-files");

            $path = $request->image->storePubliclyAs(
                'certificates_of_registration',
                $filename . "." . $request->image->extension(),
                $disk,
            );

            if (!($this->getFile($disk, $path))) {
                Log::error("Failed to update student ID " . $student->id . "'s certificate of registration. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $cor = new StudentFile();

            $cor->administrator_id = $user->id;
            $cor->student_id = $student->id;
            $cor->disk = $disk;
            $cor->type = $originalRecord['type'];
            $cor->description = '';
            $cor->path = $path;
            $cor->extension = $request->image->extension();
            $cor->year = $originalRecord['year'];
            $cor->course = $originalRecord['course'];
            $cor->term = $originalRecord['term'];
            $cor->slug = $filename;

            $cor->save();

            if (!($cor)) {
                Log::error("Failed to update student's certificate of registration.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $cor['path'] = Storage::disk($cor->disk)->url($cor->path) ?? '';

            Log::info("Successfully updated student ID " . $student->id . "'s certificate of registration. Leaving CorController studentCorUpdate...\n");
            return $this->successResponse("details", $cor);
        } catch (\Exception $e) {
            Log::error("Failed to update student's certificate of registration. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentCorDestroy(Request $request) {
        Log::info("Entering CorController studentCorDestroy...\n");

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

            if (!($user->is_admin)) {
                Log::error("User is not flagged as an admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $cor = $this->getRecord("student-files", $request->slug);
            $disk = "digital_ocean";

            if (!($this->getFile($disk, $cor->path))) {
                Log::error("Failed to soft delete student ID " . $student->id . "'s certificate of registration. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalRecord = $cor->getOriginal();
            $cor->delete();

            // Soft delete existing file
            if (StudentFile::find($originalRecord['id'])) {
                Log::error("Failed to soft delete student ID " . $student->id . "'s certificate of registration. File was not soft deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            // Set existing file to private
            Storage::disk($originalRecord['disk'])->setVisibility($originalRecord['path'], 'private');

            Log::info("Successfully soft deleted student ID " . $student->id . "'s certificate of registration. Leaving CorController studentCorDestroy...\n");
            return $this->successResponse("details", [
                'slug' => $originalRecord['slug'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to soft delete student's certificate of registration. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
