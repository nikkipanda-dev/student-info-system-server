<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\StudentFile;
use App\Models\StudentRegistrarFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class RegistrarFileController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function studentRegistrarFileGetAll(Request $request) {
        Log::info("Entering RegistrarFileController studentRegistrarFileGetAll...\n");

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

            $registrarFiles = StudentRegistrarFile::with('studentFiles')
                                                  ->where('student_id', $student->id)
                                                  ->get();

            if (count($registrarFiles) === 0) {
                Log::notice("No registrar files yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            foreach ($registrarFiles as $registrarFile) {
                $files = [];

                $ctr = 0;
                foreach ($registrarFile->studentFiles as $file) {
                    ++$ctr;

                    $files[] = [
                        'id' => $ctr,
                        'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                        'slug' => $file->slug,
                    ];
                }

                $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
                $registrarFile = $this->unsetFromArray($registrarFile, $keys);
                $registrarFile['files'] = $files;
                $formattedArr[] = $registrarFile;
            }

            Log::info("Successfully retrieved student's registrar files. Leaving RegistrarFileController studentRegistrarFileGetAll...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve student's registrar files. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentRegistrarFileStore(Request $request) {
        Log::info("Entering RegistrarFileController studentRegistrarFileStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'description' => 'bail|required|string|min:2|max:500',
            'registrar_files' => 'bail|required|array|min:1',
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

            if (!($request->hasFile('registrar_files'))) {
                Log::error("Registrar file images do not exist on the request.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $filename = $this->generateSlug("student-registrar-files");

            $newRegistrarFile = new StudentRegistrarFile();

            $newRegistrarFile->administrator_id = $user->id;
            $newRegistrarFile->student_id = $student->id;
            $newRegistrarFile->description = $request->description;
            $newRegistrarFile->course = $student->course;
            $newRegistrarFile->year = $student->year;
            $newRegistrarFile->term = $student->term;
            $newRegistrarFile->status = $request->status;
            $newRegistrarFile->slug = $filename;

            $newRegistrarFile->save();

            if (!($newRegistrarFile)) {
                Log::error("Failed to store student's registrar file details.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $transactionResponse = DB::transaction(function () use ($request, $student, $user, $newRegistrarFile) {
                $disk = "digital_ocean";

                foreach ($request->registrar_files as $registrarFile) {
                    $isValid = false;
                    $errorText = null;
                    $array = [];

                    if (!($registrarFile->isValid())) {
                        Log::error("Failed to store student ID " . $student->id . "'s registrar file. File is invalid.\n");
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        break;
                    }

                    $filename = $this->generateSlug("student-files");

                    $path = $registrarFile->storePubliclyAs(
                        'registrar_files',
                        $filename . "." . $registrarFile->extension(),
                        $disk,
                    );

                    if (!($this->getFile($disk, $path))) {
                        Log::error("Failed to store student ID " . $student->id . "'s registrar file. File was not saved to disk.\n");
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        break;
                    }

                    $file = new StudentFile();

                    $file->administrator_id = $user->id;
                    $file->student_id = $student->id;
                    $file->student_registrar_file_id = $newRegistrarFile->id;
                    $file->disk = $disk;
                    $file->type = "registrar_file";
                    $file->description = $newRegistrarFile->description;
                    $file->path = $path;
                    $file->extension = $registrarFile->extension();
                    $file->course = $student->course;
                    $file->year = $student->year;
                    $file->term = $student->term;
                    $file->slug = $filename;

                    $file->save();

                    if (!($file)) {
                        Log::error("Failed to store student ID " . $student->id . "'s registrar file details.\n");
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
                Log::error("Failed to store student ID " . $student->id . "'s registrar file.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $newRegistrarFile->refresh();

            $ctr = 0;
            foreach ($newRegistrarFile->studentFiles as $file) {
                ++$ctr;

                $files[] = [
                    'id' => $ctr,
                    'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                    'slug' => $file->slug,
                ];
                $newRegistrarFile['files'] = $files;
            }

            $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
            $newRegistrarFile = $this->unsetFromArray($newRegistrarFile, $keys);

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " created a new registrar file for student number" . $student->student_number . ". New registrar file slug: " . $newRegistrarFile['slug'] . ".\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully stored student ID " . $student->id . "'s registrar file. Leaving RegistrarFileController studentRegistrarFileStore...\n");

            return $this->successResponse("details", $newRegistrarFile);
        } catch (\Exception $e) {
            Log::error("Failed to store student's registrar files. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentRegistrarFileUpdate(Request $request) {
        Log::info("Entering RegistrarFileController studentRegistrarFileUpdate...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_registrar_files',
            'description' => 'bail|required|string|min:2|max:500',
            'registrar_files' => 'bail|nullable|array|min:1',
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

            $registrarFile = StudentRegistrarFile::with("studentFiles")
                                                 ->where('slug', $request->slug)
                                                 ->first();

            if (!($registrarFile)) {
                Log::error("Registrar file does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $registrarFile->status = $request->status;
            $registrarFile->description = $request->description;

            $registrarFile->save();

            if ($request->hasFile('registrar_files')) {
                // Soft delete previous and store new files
                $transactionResponse = DB::transaction(function () use ($registrarFile, $request, $student, $user) {
                    $isValid = null;
                    $errorText = '';
                    $originalRecord = $registrarFile->getOriginal();

                    // Soft delete each student's previous registrar file
                    foreach ($registrarFile->studentFiles as $file) {
                        $isValid = false;
                        $errorText = '';

                        $originalFile = $file->getOriginal();
                        $file->delete();

                        if (StudentFile::find($originalFile['id'])) {
                            Log::error("Failed to soft delete student's registrar file ID " . $originalFile['id'] . ". Student registrar file still exists.\n");
                            $errorText = $this->getPredefinedResponse([
                                'type' => 'default',
                            ]);

                            break;
                        }

                        Storage::disk($originalFile['disk'])->setVisibility($originalFile['path'], 'private');

                        $isValid = true;
                        Log::info("Soft deleted student registrar file ID " . $originalFile['id'] . ".\n");
                    }

                    if (!($isValid)) {
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        throw new Exception("Failed to soft delete student's registrar file ID " . $originalFile['id'] . ". Unable to soft delete one of the registrar files.\n");
                    } else {
                        // store each student's new file
                        $disk = "digital_ocean";

                        foreach ($request->registrar_files as $file) {
                            $isValid = false;
                            $errorText = null;
                            $array = [];

                            if (!($file->isValid())) {
                                Log::error("Failed to store student ID " . $student->id . "'s registrar file. File is invalid.\n");
                                $errorText = $this->getPredefinedResponse([
                                    'type' => 'default',
                                ]);

                                break;
                            }

                            $filename = $this->generateSlug("student-files");

                            $path = $file->storePubliclyAs(
                                'registrar_files',
                                $filename . "." . $file->extension(),
                                $disk,
                            );

                            if (!($this->getFile($disk, $path))) {
                                Log::error("Failed to store student ID " . $student->id . "'s registrar file. File was not saved to disk.\n");
                                $errorText = $this->getPredefinedResponse([
                                    'type' => 'default',
                                ]);

                                break;
                            }

                            $newFile = new StudentFile();

                            $newFile->administrator_id = $user->id;
                            $newFile->student_id = $student->id;
                            $newFile->student_registrar_file_id = $registrarFile->id;
                            $newFile->disk = $disk;
                            $newFile->type = "registrar_file";
                            $newFile->description = $registrarFile->description;
                            $newFile->path = $path;
                            $newFile->extension = $file->extension();
                            $newFile->course = $registrarFile->course;
                            $newFile->year = $registrarFile->year;
                            $newFile->term = $registrarFile->term;
                            $newFile->slug = $filename;

                            $newFile->save();

                            if (!($newFile)) {
                                Log::error("Failed to update student ID " . $student->id . "'s registrar file details.\n");
                                $errorText = $this->errorResponse($this->getPredefinedResponse([
                                    'type' => 'default',
                                ]));

                                break;
                            }

                            $array[] = $this->getFileUrl($disk, $path);
                            $isValid = true;
                        }

                        $isValid = true;
                    }

                    return [
                        "is_valid" => $isValid,
                        "error_text" => $errorText,
                        "registrar_file" => $originalRecord,
                    ];
                }, 3);

                if (!($transactionResponse['is_valid'])) {
                    Log::error("Failed to update student's registrar file details. Database transaction failed.\n");
                    return $this->errorResponse($transactionResponse['error_text']);
                }
            }

            $registrarFile->refresh();
            $ctr = 0;

            foreach ($registrarFile->studentFiles as $file) {
                ++$ctr;

                $files[] = [
                    'id' => $ctr,
                    'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                    'slug' => $file->slug,
                ];
                $registrarFile['files'] = $files;
            }

            $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
            $registrarFile = $this->unsetFromArray($registrarFile, $keys);

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " updated a registrar file for student number" . $student->student_number . ". Registrar file slug: " . $registrarFile['slug'] .".\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully updated student ID " . $student->id . "'s registrar file. Leaving RegistrarFileController studentRegistrarFileUpdate...\n");

            return $this->successResponse("details", [
                'description' => $registrarFile['description'],
                'status' => $registrarFile['status'],
                'files' => $registrarFile['files'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update student's registrar file. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentRegistrarFileDestroy(Request $request) {
        Log::info("Entering RegistrarFileController studentRegistrarFileDestroy...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'student_slug' => 'bail|required|exists:students,slug',
            'slug' => 'bail|required|exists:student_registrar_files',
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

            $registrarFile = StudentRegistrarFile::with("studentFiles")
                                                 ->where('slug', $request->slug)
                                                 ->first();

            if (!($registrarFile)) {
                Log::error("Registrar file does not exist or might be deleted.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            $transactionResponse = DB::transaction(function () use ($registrarFile) {
                $isValid = null;
                $errorText = '';
                $originalRecord = $registrarFile->getOriginal();
                $originalFile = '';

                // Soft delete each student's payment file
                foreach ($registrarFile->studentFiles as $file) {
                    $isValid = false;
                    $errorText = '';

                    $originalFile = $file->getOriginal();
                    $file->delete();

                    if (StudentFile::find($originalFile['id'])) {
                        $errorText = $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        throw new Exception("Failed to soft delete student's registrar file ID " . $originalFile['id'] . ". Student registrar file still exists.\n");

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
                    $registrarFile->delete();

                    if (StudentRegistrarFile::find($originalRecord['id'])) {
                        $isValid = false;
                        return $this->getPredefinedResponse([
                            'type' => 'default',
                        ]);

                        throw new Exception("Failed to soft delete student's registrar file ID " . $originalRecord['id'] . ".\n");
                    }

                    $isValid = true;
                    Log::info("Soft deleted student's registrar file ID " . $originalRecord['id'] . ".\n");
                }

                return [
                    "is_valid" => $isValid,
                    "error_text" => $errorText,
                    "registrar_file" => $originalRecord,
                ];
            }, 3);

            if (!($transactionResponse['is_valid'])) {
                Log::error("Failed to soft delete student's registrar file.\n");
                return $this->errorResponse($transactionResponse['error_text']);
            }

            $message = "Administrator " . Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " deleted a registrar file from student number" . $student->student_number . ". Registrar file slug: " . $transactionResponse['registrar_file']['slug'] . "\n";
            $this->logResponses($user->id, $student->id, $message, $page);
            Log::info("Successfully soft deleted student ID " . $student->id . "'s registrar file. Leaving RegistrarFileController studentRegistrarFileDestroy...\n");

            return $this->successResponse("details", [
                'slug' => $transactionResponse['registrar_file']['slug'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to soft delete student's registrar file. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    // Authenticated student
    public function studentAuthRegistrarFileGetAll(Request $request) {
        Log::info("Entering RegistrarFileController studentAuthRegistrarFileGetAll...\n");

        $this->validate($request, [
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

            if (!($student->is_enrolled)) {
                Log::error("Student is not flagged as enrolled.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $registrarFiles = StudentRegistrarFile::with('studentFiles')
                                                  ->where('student_id', $student->id)
                                                  ->get();

            if (count($registrarFiles) === 0) {
                Log::notice("No registrar files yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            foreach ($registrarFiles as $registrarFile) {
                $files = [];

                $ctr = 0;
                foreach ($registrarFile->studentFiles as $file) {
                    ++$ctr;

                    $files[] = [
                        'id' => $ctr,
                        'path' => Storage::disk($file->disk)->url($file->path) ?? '',
                        'slug' => $file->slug,
                    ];
                }

                $keys = ['id', 'updated_at', 'deleted_at', 'administrator_id', 'student_id', 'student_files'];
                $registrarFile = $this->unsetFromArray($registrarFile, $keys);
                $registrarFile['files'] = $files;
                $formattedArr[] = $registrarFile;
            }

            Log::info("Successfully retrieved authenticated student's registrar files. Leaving RegistrarFileController studentAuthRegistrarFileGetAll...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve authenticated student's registrar files. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
