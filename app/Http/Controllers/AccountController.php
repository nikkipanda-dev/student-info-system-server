<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    use ResponseTrait;

    public function adminStore(Request $request) {
        Log::info("Entering AccountController adminStore...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
            'first_name' => 'bail|required|min:2|max:200',
            'middle_name' => 'bail|nullable|min:2|max:200',
            'last_name' => 'bail|required|min:2|max:200',
            'email' => 'bail|required|email',
            'password' => 'bail|required',
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

            $admin = new Administrator();

            $admin->first_name = $request->first_name;
            $admin->middle_name = $request->middle_name ?? '';
            $admin->last_name = $request->last_name;
            $admin->email = $request->email;
            $admin->password = Hash::make($request->password);

            $admin->save();

            if (!($admin)) {
                Log::error("Failed to store new admin.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'default',
                ]));
            }

            Log::info("Successfully stored new admin ID ".$admin->id. ". Leaving AccountController adminStore...\n");
            return $this->successResponse("details", $admin);
        } catch (\Exception $e) {
            Log::error("Failed to store new admin. ".$e->getMessage().".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
