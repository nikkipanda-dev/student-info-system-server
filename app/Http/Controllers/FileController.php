<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Traits\RecordTrait;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    use ResponseTrait, RecordTrait;

    public function studentFileDownload($admin_slug, $student_slug, $file_slug) {
        Log::info("Entering FileController studentFileDownload...\n");

        try {
            if (!($admin_slug) || !($student_slug)) {
                return "Unauthorized action";
            }

            if (!($file_slug)) {
                return "No file to search.";
            }

            $user = Administrator::where('slug', $admin_slug)->first();
            $student = $this->getRecord('students', $student_slug);

            if (!($user)) {
                Log::error("User does not exist on our system.\n");
                return $this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'administrator',
                ]);
            }

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return $this->getPredefinedResponse([
                    'type' => 'not-found',
                    'content' => 'student',
                ]);
            }

            $cor = $this->getRecord("student-files", $file_slug);
            $disk = "digital_ocean";

            if (!($cor)) {
                Log::error("Failed to download student ID " . $student->id . "'s certificate of registration. File does not exist or might be deleted.\n");
                return $this->getPredefinedResponse([
                    'type' => 'default',
                ]);
            }

            if (!($this->getFile($disk, $cor->path))) {
                Log::error("Failed to download student ID " . $student->id . "'s certificate of registration. File was not saved to disk.\n");
                return $this->getPredefinedResponse([
                    'type' => 'default',
                ]);
            }

            Log::info("Successfully downloaded student ID " . $student->id . "'s certificate of registration. Leaving FileController studentFileDownload...\n");
            return Storage::disk($cor->disk)->download($cor->path, "test", [
                'Content-Type' => "application/".$cor->extension,
                'Content-Disposition' => 'attachment; filename="'.$student->last_name."_".$student->student_number."_".$cor->type.".". $cor->extension,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to download student's certificate of registration. " . $e->getMessage() . ".\n");
            return $this->getPredefinedResponse([
                'type' => 'default',
            ]);
        }
    }
}
