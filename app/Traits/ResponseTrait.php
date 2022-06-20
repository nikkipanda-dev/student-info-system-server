<?php

namespace App\Traits;

use Illuminate\Support\Str;

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
        ];

        return $responses[$data['type']];
    }
}