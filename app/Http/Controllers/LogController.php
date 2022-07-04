<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\UserLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ResponseTrait;
use App\Traits\RecordTrait;

class LogController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function getUserLogs(Request $request) {
        Log::info("Entering LogController getUserLogs...\n");

        $this->validate($request, [
            'auth_email' => 'bail|required|exists:administrators,email',
        ]);

        try {
            $authUser = Administrator::where('email', $request->auth_email)->first();

            if (!($authUser)) {
                Log::error("User does not exist on our system.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'user',
                ]));
            }

            if (!($authUser->is_super_admin)) {
                $message = "Administrator " . Str::ucfirst($authUser->first_name) . " " . Str::ucfirst($authUser->last_name) . " is not flagged as a super admin. ID: " . $authUser->id . ".\n";
                Log::error($message);

                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'unauth',
                ]));
            }

            $userLogs = UserLog::get();

            if (count($userLogs) === 0) {
                Log::notice("No user logs yet.\n");
                return $this->errorResponse($this->getPredefinedResponse([
                    'type' => 'empty',
                ]));
            }

            $formattedArr = [];
            $ctr = 0;
            foreach ($userLogs as $userLog) {
                $files = [];

                ++$ctr;

                $keys = [
                    'id',
                    'administrator_id',
                    'student_id',
                    'updated_at',
                    'deleted_at',
                ];

                $userLog['file'] = $files;
                $userLog = $this->unsetFromArray($userLog, $keys);
                $formattedArr[] = $userLog;
            }

            Log::info("Successfully retrieved user logs. Leaving LogController getUserLogs...\n");
            return $this->successResponse("details", $formattedArr);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve user logs. " . $e->getMessage() . ".\n");
            return $this->errorResponse($this->getPredefinedResponse([
                'type' => 'default',
            ]));
        }
    }
}
