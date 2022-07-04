<?php

namespace App\Traits;

use Illuminate\Support\Str;
use App\Models\UserLog;
use Illuminate\Support\Facades\Log;

trait ResponseTrait {
    public function successResponse($label, $data) {
        return [
            "is_success" => true,
            "data" => [
                $label => $data,
            ]            
        ];
    }

    public function errorResponse($data) {
        return [
            "is_success" => false,
            "data" => $data,
        ];
    }

    public function getPredefinedResponse($data) {
        $responses = [
            "default" => "Something went wrong. Please again in a few seconds or contact the web admin directly for assistance.",
            "not-found" => Str::ucfirst(isset($data['content']) ? $data['content'] : '')." does not exist or might be deleted.",
            "incorrect-pw" => "Password is incorrect. Please try again.",
            "empty" => "No data to show",
            "unauth" => "You do not have permission to access this content.",
            "not-changed" => Str::ucfirst(isset($data['content']) ? $data['content'] : '')." was not changed.",
        ];

        return $responses[$data['type']];
    }

    public function getTokenId($bearerToken, $user) {
        $isBearerTokenMatch = null;

        preg_match("/^[^|]*/", $bearerToken, $matches);

        foreach($user->tokens as $token) {
            if (isset($matches[0]) && (intval($matches[0], 10) === intval($token->id, 10)) && ($token->tokenable_id === $user->id)) {
                $isBearerTokenMatch = intval($token->id, 10);
            }
        }

        return $isBearerTokenMatch;
    }

    public function revokeToken($tokenId, $user) {
        $user->tokens()->where('id', $tokenId)->delete();
    }

    public function logResponses($adminId, $studentId, $description, $page) {
        if (!($description)) {
            Log::error("No description for user logs.");
        }

        if (!($page)) {
            Log::error("No page for user logs.");
        }

        $userLog = new UserLog();

        if ($adminId) {
            $userLog->administrator_id = $adminId;
        }

        if ($studentId) {
            $userLog->student_id = $studentId;
        }

        $userLog->description = $description;
        $userLog->page = $page;

        $userLog->save();
    }
}