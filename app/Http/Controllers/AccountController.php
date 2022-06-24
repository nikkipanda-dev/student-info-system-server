<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use Illuminate\Support\Facades\Storage;
use App\Traits\ResponseTrait;
use App\Traits\AdminTrait;
use App\Traits\RecordTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    use ResponseTrait, AdminTrait, RecordTrait;

    public function adminStore(Request $request) {
        Log::info("Entering AccountController adminStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'first_name' => 'bail|required|min:2|max:200',
            'middle_name' => 'bail|nullable|min:2|max:200',
            'last_name' => 'bail|required|min:2|max:200',
            'email' => 'bail|required|email|unique:administrators',
            'password' => 'bail|required|string|min:8|max:20',
            'password_confirmation' => 'bail|required',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'super administrator',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $admin = new Administrator();

            $admin->first_name = $request->first_name;
            $admin->middle_name = $request->middle_name ?? '';
            $admin->last_name = $request->last_name;
            $admin->email = $request->email;
            $admin->password = Hash::make($request->password);
            $admin->slug = $this->generateSlug('administrators');

            $admin->save();

            if (!($admin)) {
                Log::error("Failed to store new administrator.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully stored new administrator ID ".$admin->id. ". Leaving AccountController adminStore...\n");
            return $this->successResponse("details", $admin->refresh());
        } catch (\Exception $e) {
            Log::error("Failed to store new administrator. ".$e->getMessage().".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function adminGetAll(Request $request) {
        Log::info("Entering AccountController adminGetAll...\n");

        try {
            $users = $this->getAllAdmins();

            if (count($users) === 0) {
                Log::notice("No administrators yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            Log::info("Successfully retrieved administrators. Leaving AccountController adminGetAll...\n");
            return $this->successResponse("details", $users);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve administrators. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function adminToggleStatus(Request $request) {
        Log::info("Entering AccountController adminToggleStatus...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'email' => 'bail|required|exists:administrators',
        ]);

        try {
            $authUser = Administrator::where('email', $request->auth_email)->first();
            $user = Administrator::where('email', $request->email)->first();

            if (!($user) || !($authUser)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $authUser);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($authUser->is_super_admin)) {
                Log::error("Authenticated user is not super admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $originalStatus = $user->getOriginal('is_admin');

            $user->is_admin = !($user->is_admin);

            $user->save();

            if (!($user->wasChanged('is_admin'))) {
                Log::error("Failed to remove as administrator.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully updated administrator ID " . $user->id . "'s status from ".$originalStatus." to ".$user->is_admin.". Leaving AccountController adminToggleStatus...\n");
            return $this->successResponse("details", $user->is_admin);
        } catch (\Exception $e) {
            Log::error("Failed to remove as administrator. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    // Student
    public function studentGetAll() {
        Log::info("Entering AccountController studentGetAll...\n");

        try {
            $users = $this->getAllStudents();

            if (count($users) === 0) {
                Log::notice("No students yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            Log::info("Successfully retrieved students. Leaving AccountController studentGetAll...\n");
            return $this->successResponse("details", $users);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve students. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentGet(Request $request) {
        Log::info("Entering AccountController studentGet...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $student = $this->getRecord("students", $request->slug);

            if (!($student)) {
                Log::notice("Student does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            $file = StudentFile::where('student_id', $student->id)
                               ->where('type', "display_photo")
                               ->first();

            $student['display_photo'] = $file ? $this->getFileUrl("digital_ocean", $file->path) : null;

            Log::info("Successfully retrieved student ID ".$student->id. ". Leaving AccountController studentGet...\n");
            return $this->successResponse("details", $student);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentStore(Request $request) {
        Log::info("Entering AccountController studentStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'first_name' => 'bail|required|min:2|max:200',
            'middle_name' => 'bail|nullable|min:2|max:200',
            'last_name' => 'bail|required|min:2|max:200',
            'student_number' => 'bail|required|string|min:2|max:200|unique:students',
            'course' => 'bail|required|in:bsit,bscs,bsis,bsba',
            'year' => 'bail|required|regex:/^\d{4}$/',
            'term' => 'bail|required|numeric|in:1,2,3',
            'email' => 'bail|required|email|unique:students',
            'password' => 'bail|required|string|min:8|max:20',
            'password_confirmation' => 'bail|required',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $student = new Student();

            $student->first_name = $request->first_name;
            $student->middle_name = $request->middle_name ?? '';
            $student->last_name = $request->last_name;
            $student->student_number = $request->student_number;
            $student->course = $request->course;
            $student->year = $request->year;
            $student->term = $request->term;
            $student->email = $request->email;
            $student->password = Hash::make($request->password);
            $student->slug = $this->generateSlug('students');

            $student->save();

            if (!($student)) {
                Log::error("Failed to store new student.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully stored new student ID " . $student->id . ". Leaving AccountController studentStore...\n");
            return $this->successResponse("details", $student->refresh());
        } catch (\Exception $e) {
            Log::error("Failed to store new student. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentNameUpdate(Request $request) {
        Log::info("Entering AccountController studentNameUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
            'first_name' => 'bail|required|min:2|max:200',
            'middle_name' => 'bail|nullable|min:2|max:200',
            'last_name' => 'bail|required|min:2|max:200',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->slug);

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

            $student->first_name = $request->first_name;

            if ($request->middle_name) {
                $student->middle_name = $request->middle_name;
            }

            $student->last_name = $request->last_name;

            $student->save();

            Log::info("Successfully updated student ID " . $student->id . "'s name. Leaving AccountController studentNameUpdate...\n");
            return $this->successResponse("details", $student->only(['first_name', 'middle_name', 'last_name']));
        } catch (\Exception $e) {
            Log::error("Failed to update student's name. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentDisplayPhotoUpdate(Request $request) {
        Log::info("Entering AccountController studentDisplayPhotoUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
            'image' => 'bail|required|image',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->slug);

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

            if (!($request->hasFile('image'))) {
                Log::error("File does not exist.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            if (!($request->image->isValid())) {
                Log::error("File is invalid.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $file = StudentFile::latest()
                               ->where('student_id', $student->id)
                               ->first();

            if ($file) {
                $originalId = $file->getOriginal('id');

                $file->delete();

                if (StudentFile::find($originalId)) {
                    Log::error("Failed to update student ID ".$student->id."'s display photo.\n");
                    return $this->errorResponse($this->getPredefinedResponse([
                        'type' => 'default',
                    ]));
                }
            }

            $filename = $this->generateSlug("student-files");
            $disk = "digital_ocean";

            $path = Storage::disk($disk)->putFileAs(
                'display_photo',
                $request->image,
                $filename.".".$request->image->extension(),
            );

            if (!($this->getFile($disk, $path))) {
                Log::error("Failed to update student ID " . $student->id . "'s display photo. File was not saved to disk.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $isFilePublic = $this->setFilePublic($disk, $path);

            if (!($isFilePublic)) {
                Log::error("Failed to set student ID " . $student->id . "'s display photo as public.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $image = new StudentFile();

            $image->administrator_id = $user->id;
            $image->student_id = $student->id;
            $image->disk = $disk;
            $image->type = "display_photo";
            $image->description = '';
            $image->path = $path;
            $image->extension = $request->image->extension();
            $image->course = $student->course;
            $image->year = $student->year;
            $image->term = $student->term;
            $image->slug = $filename;

            $image->save();

            if (!($image)) {
                Log::error("Failed to set student ID " . $student->id . "'s display photo.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully updated student ID " . $student->id . "'s display photo. Leaving AccountController studentDisplayPhotoUpdate...\n");
            return $this->successResponse("details", $this->getFileUrl($disk, $path));
        } catch (\Exception $e) {
            Log::error("Failed to update student's display photo. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentEmailUpdate(Request $request) {
        Log::info("Entering AccountController studentEmailUpdate...\n");

        $student = $this->getRecord('students', $request->slug);

        if (!($student)) {
            Log::error("Student does not exist on our system.\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'not-found',
                'content' => 'student',
            ]));
        }

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
            'email' => [
                'bail',
                'required',
                'email',
                Rule::unique('students')->ignore($student),
            ],
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]));
            }

            $tokenId = $this->getTokenId($request->bearerToken(), $user);

            if (!($tokenId)) {
                Log::error("Bearer token is missing and/or user-token did not match.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $student->email = $request->email;

            $student->save();

            Log::info("Successfully updated student ID " . $student->id . "'s email address. Leaving AccountController studentEmailUpdate...\n");
            return $this->successResponse("details", $student->email);
        } catch (\Exception $e) {
            Log::error("Failed to update student's email address. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentPasswordUpdate(Request $request) {
        Log::info("Entering AccountController studentPasswordUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'slug' => 'bail|required|exists:students',
            'password' => 'bail|required|string|min:8|max:20',
            'password_confirmation' => 'bail|required',
        ]);

        try {
            $user = Administrator::where('email', $request->auth_email)->first();
            $student = $this->getRecord('students', $request->slug);

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

            $student->password = Hash::make($request->password);

            $student->save();

            Log::info("Successfully updated student ID " . $student->id . "'s password. Leaving AccountController studentPasswordUpdate...\n");
            return $this->successResponse("details", $student->only(['first_name', 'middle_name', 'last_name']));
        } catch (\Exception $e) {
            Log::error("Failed to update student's password. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
