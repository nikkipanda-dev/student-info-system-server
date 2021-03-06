<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;

class AuthController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function authenticate(Request $request) {
        Log::info("Entering AuthController authenticate...\n");

        $this->validate($request, [
            'email' => 'bail|required',
            'password' => 'bail|required',
        ]);
        
        $page = "/admin";

        try {
            $user = Administrator::where('email', $request->email)->first();

            if (!($user)) {
                $message = "Administrator does not exist on our system. Provided email ".$request->email." and password ".$request->password.".\n";
                $this->logResponses(null, null, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            if (!(Hash::check($request->password, $user->password))) {
                $message = "Password is incorrect. Provided email " . $request->email . ".\n";
                $this->logResponses($user->id, null, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'incorrect-pw',
                ]));
            }

            if (!($user->is_admin)) {
                $message = "Administrator is not flagged as an admin.\n";
                $this->logResponses($user->id, null, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $token = $user->createToken("auth_admin_token")->plainTextToken;
            $message = "Admin ".Str::ucfirst($user->first_name)." ".Str::ucfirst($user->last_name)." logged in. ID: ".$user->id.".\n";
            $this->logResponses($user->id, null, $message, $page);
            Log::info("Successfully authenticated administrator ID " . $user->id . ". AuthController authenticate...\n");

            return $this->successResponse("details", [
                'user' => $user,
                'token' => $token,
            ]);
        } catch(\Exception $e) {
            $message = "Failed to authenticated user. " . $e->getMessage() . ".\n";
            Log::error($message);

            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function authenticateStudent(Request $request) {
        Log::info("Entering AuthController authenticateStudent...\n");

        $this->validate($request, [
            'email' => 'bail|required',
            'password' => 'bail|required',
        ]);

        $page = "student app index";

        try {
            $user = Student::where('email', $request->email)->first();

            if (!($user)) {
                $message = "Student does not exist on our system. Provided email " . $request->email . " and password " . $request->password . ".\n";
                $this->logResponses(null, null, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            if (!(Hash::check($request->password, $user->password))) {
                $message = "Password is incorrect. Provided email " . $request->email . ".\n";
                $this->logResponses(null, $user->id, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'incorrect-pw',
                ]));
            }

            if (!($user->is_enrolled)) {
                $message = "User is not flagged as enrolled.\n";
                $this->logResponses(null, $user->id, $message, $page);
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $file = StudentFile::where('student_id', $user->id)
                               ->where('type', "display_photo")
                               ->first();

            $user['display_photo'] = $file ? $this->getFileUrl("digital_ocean", $file->path) : null;

            $token = $user->createToken("auth_student_token")->plainTextToken;

            $message = "Student ". Str::ucfirst($user->first_name) . " " . Str::ucfirst($user->last_name) . " logged in. Student number: " . $user->student_number . "\n";
            $this->logResponses(null, $user->id, $message, $page);
            Log::info("Successfully authenticated student ID " . $user->id . ". AuthController authenticateStudent...\n");

            return $this->successResponse("details", [
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            $message = "Failed to authenticated student. " . $e->getMessage() . ".\n";
            Log::error($message);

            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function adminLogout(Request $request) {
        Log::info("Entering AuthController adminLogout func...\n");

        $this->validate($request, [
            'email' => 'bail|required|exists:administrators',
        ]);

        $page = "admin app";

        try {
            $user = Administrator::where('email', $request->email)->first();

            if (!($user)) {
                $message = "Administrator does not exist on our system. Provided email " . $request->email . ".\n";
                $this->logResponses(null, null, $message, $page);
                Log::error($message);

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

            $this->revokeToken($tokenId, $user);
            $originalUser = $user->getOriginal();

            $message = "Admin " . Str::ucfirst($originalUser['first_name']). " " . Str::ucfirst($originalUser['last_name']). " signed out. ID: " . $originalUser['id'] . ".\n";
            $this->logResponses($originalUser['id'], null, $message, $page);
            Log::info("Successfully logged out user ID ". $originalUser['id'].". AuthController adminLogout...\n");

            return $this->successResponse(null, null);
        } catch (\Exception $e) {
            $message = "Failed to logout administrator. " . $e->getMessage() . ".\n";
            Log::error($message);

            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function studentLogout(Request $request) {
        Log::info("Entering AuthController studentLogout...\n");

        $this->validate($request, [
            'email' => 'bail|required|exists:students',
        ]);

        $page = "student app";

        try {
            $user = Student::where('email', $request->email)->first();

            if (!($user)) {
                $message = "Student does not exist on our system. Provided email " . $request->email . ".\n";
                $this->logResponses(null, null, $message, $page);
                Log::error($message);

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

            $this->revokeToken($tokenId, $user);
            $originalUser = $user->getOriginal();

            $message = "Student " . Str::ucfirst($originalUser['first_name']) . " " . Str::ucfirst($originalUser['last_name']) . " signed out. Student number: " . $originalUser['student_number'] . "\n";
            $this->logResponses(null, $originalUser['id'], $message, $page);
            Log::info("Successfully logged out student ID " . $originalUser['id'] . ". AuthController studentLogout...\n");

            return $this->successResponse(null, null);
        } catch (\Exception $e) {
            $message = "Failed to logout student. " . $e->getMessage() . ".\n";
            Log::error($message);
            
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function test(Request $request) {
        Log::info("Entering test");
        return response('test ok', 200);
    }
}
