<?php

namespace App\Traits;

use App\Models\Administrator;
use App\Models\Student;
use App\Models\StudentFile;
use App\Models\StudentPayment;
use App\Models\StudentRegistrarFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Array_;

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
                "student-files" => StudentFile::withTrashed()
                                              ->where("slug", $slug)
                                              ->first(),
                "student-payments" => StudentPayment::withTrashed()
                                                    ->where("slug", $slug)
                                                    ->first(),
                "student-registrar-files" => StudentRegistrarFile::withTrashed()
                                                                 ->where("slug", $slug)
                                                                 ->first(),
            ];

            if (!($models[$model])) {
                $isUnique = true;
            }
        } while (!($isUnique));

        return $slug;
    }

    public function getRecord($model, $slug) {
        Log::info("Entering RecordTrait getRecord...\n");

        if (!($model) || !($slug)) {
            Log::error("No data model and/or slug provided.\n");
            return;
        }

        $models = [
            "administrators" => Administrator::where("slug", $slug)->first(),
            "students" => Student::where("slug", $slug)->first(),
            "student-files" => StudentFile::where("slug", $slug)->first(),
            "student-payments" => StudentPayment::where("slug", $slug)->first(),
            "student-registrar-files" => StudentRegistrarFile::where("slug", $slug)->first(),
        ];

        return $models[$model];
    }

    public function setFilePublic($disk, $path) {
        $isPublic = false;

        Storage::disk($disk)->setVisibility($path, 'public');

        if (Storage::disk($disk)->getVisibility($path) === "public") {
            $isPublic = true;
        }

        return $isPublic;
    }

    public function getFile($disk, $path) {
        $file = null;

        if (Storage::disk($disk)->exists($path)) {
            $file = Storage::disk($disk)->get($path);
        }

        return $file;
    }

    public function getFileUrl($disk, $path) {
        $url = null;

        if (Storage::disk($disk)->exists($path)) {
            $url = Storage::disk($disk)->url($path);
        }

        return $url;
    }

    public function unsetFromArray(Object $array, Array $keys) {
        $decoded = json_decode(json_encode($array), true);

        foreach ($keys as $key) {
            if (array_key_exists($key, $decoded)) {
                unset($decoded[$key]);
            }
        }

        return $decoded;
    }
}