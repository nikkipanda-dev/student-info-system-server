<?php

namespace App\Traits;

use App\Models\Administrator;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

trait RecordTrait {
    public function generateSlug($model) {
        Log::info("Entering RecordTrait generateSlug...\n");

        $slug = null;
        $isUnique = false;

        if (!($model)) {
            Log::error("No data model provided.\n");
            return;
        }

        do {
            $slug = bin2hex(random_bytes(15));

            $models = [
                "administrators" => Administrator::withTrashed()
                                                 ->where("slug", $slug)
                                                 ->first(),
                "students" => Student::withTrashed()
                                     ->where("slug", $slug)
                                     ->first(),
            ];

            if (!($models[$model])) {
                $isUnique = true;
            }
        } while (!($isUnique));

        return $slug;
    }
}