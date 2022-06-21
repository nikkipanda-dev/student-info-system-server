<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Traits\ResponseTrait;
use App\Traits\AdminTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    use ResponseTrait, AdminTrait;

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
}
