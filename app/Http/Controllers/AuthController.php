<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
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
                Log::error("User does not exist on our system.");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            if (!(Hash::check($request->password, $user->password))) {
                Log::error("Password is incorrect.");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'incorrect-pw',
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

    public function adminLogout(Request $request) {
        Log::info("Entering AuthController authenticate func...\n");

        $this->validate($request, [
            'email' => 'bail|required|exists:administrators',
        ]);

        try {
            $user = Administrator::where('email', $request->email)->first();

            if (!($user)) {
                Log::error("User does not exist on our system.");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            return $this->successResponse("details", $user);
        } catch (\Exception $e) {
            Log::error("Failed to logout user. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }

    public function test(Request $request) {
        return response('test ok', 200);
    }
}
