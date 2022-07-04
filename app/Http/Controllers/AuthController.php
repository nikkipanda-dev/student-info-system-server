<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Traits\ResponseTrait;

class AuthController extends Controller
{
    use ResponseTrait;

    public function authenticate(Request $request) {
        Log::info("Entering AuthController authenticate func...\n");

        $this->validate($request, [
            'email' => 'bail|required',
            'password' => 'bail|required',
        ]);

        try {
            $user = Administrator::where('email', $request->email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            if (!(Hash::check($request->password, $user->password))) {
                Log::error("Password is incorrect.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'incorrect-pw',
                ]));
            }

            if (!($user->is_super_admin) || !($user->is_admin)) {
                Log::error("User is neither flagged as a super admin or admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $token = $user->createToken("auth_admin_token")->plainTextToken;

            return $this->successResponse("details", [
                'user' => $user,
                'token' => $token,
            ]);
        } catch(\Exception $e) {
            Log::error("Failed to authenticated user. ".$e->getMessage().".\n");
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

        Log::info($request);

        try {
            $user = Student::where('email', $request->email)->first();

            if (!($user)) {
                Log::error("Student does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]));
            }

            if (!(Hash::check($request->password, $user->password))) {
                Log::error("Password is incorrect.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'incorrect-pw',
                ]));
            }

            if (!($user->is_enrolled)) {
                Log::error("User is not flagged as enrolled.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $token = $user->createToken("auth_student_token")->plainTextToken;

            Log::info("Successfully authenticated user ID " . $user->id . ". AuthController authenticateStudent...\n");

            return $this->successResponse("details", [
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to authenticated user. " . $e->getMessage() . ".\n");
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

        try {
            $user = Administrator::where('email', $request->email)->first();

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

            $this->revokeToken($tokenId, $user);
            $originalUser = $user->getOriginal();

            Log::info("Successfully logged out user ID ". $originalUser['id'].". AuthController adminLogout...\n");

            return $this->successResponse(null, null);
        } catch (\Exception $e) {
            Log::error("Failed to logout user. " . $e->getMessage() . ".\n");
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

        try {
            $user = Student::where('email', $request->email)->first();

            if (!($user)) {
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

            $this->revokeToken($tokenId, $user);
            $originalUser = $user->getOriginal();

            Log::info("Successfully logged out student ID " . $originalUser['id'] . ". AuthController studentLogout...\n");

            return $this->successResponse(null, null);
        } catch (\Exception $e) {
            Log::error("Failed to logout student. " . $e->getMessage() . ".\n");
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
