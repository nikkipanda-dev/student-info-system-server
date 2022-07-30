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
                Log::error("Administrator does not exist on our system.\n");
                return "Administrator does not exist.";
            }

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return "Student does not exist.";
            }

            if (!($user->is_admin)) {
                Log::error("User is neither flagged as a super admin nor admin.\n");
                return "Unauthorized action.";
            }

            $file = $this->getRecord("student-files", $file_slug);
            $disk = "digital_ocean";

            if (!($file)) {
                Log::error("Failed to download student ID " . $student->id . "'s file. File does not exist or might be deleted.\n");
                return "File does not exist.";
            }

            if (!($this->getFile($disk, $file->path))) {
                Log::error("Failed to download student ID " . $student->id . "'s file. File was not saved to disk.\n");
                return "File does not exist.";
            }

            Log::info("Successfully downloaded student ID " . $student->id . "'s file. Leaving FileController studentFileDownload...\n");
            return Storage::disk($file->disk)->download($file->path, "test", [
                'Content-Type' => "application/". $file->extension,
                'Content-Disposition' => 'attachment; filename="'.$student->last_name."_".$student->student_number."_". $file->type.".". $file->extension,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to download student's file. " . $e->getMessage() . ".\n");
            return "Something went wrong.";
        }
    }

    public function studentAuthFileDownload($student_slug, $file_slug) {
        Log::info("Entering FileController studentAuthFileDownload...\n");

        try {
            if (!($file_slug)) {
                return "No file to search.";
            }

            if (!($student_slug)) {
                return "Something went wrong.";
            }

            $student = $this->getRecord('students', $student_slug);

            if (!($student)) {
                Log::error("Student does not exist on our system.\n");
                return "Student does not exist.";
            }

            if (!($student->is_enrolled)) {
                Log::error("Student is not flagged as enrolled.\n");
                return "Something went wrong.";
            }

            $file = $this->getRecord("student-files", $file_slug);
            $disk = "digital_ocean";

            if (!($file)) {
                Log::error("Failed to download authenticated student ID " . $student->id . "'s file. File does not exist or might be deleted.\n");
                return "File does not exist.";
            }

            if (!($this->getFile($disk, $file->path))) {
                Log::error("Failed to download authenticated student ID " . $student->id . "'s file. File was not saved to disk.\n");
                return "File does not exist.";
            }

            Log::info("Successfully downloaded authenticated student ID " . $student->id . "'s file. Leaving FileController studentAuthFileDownload...\n");
            return Storage::disk($file->disk)->download($file->path, "test", [
                'Content-Type' => "application/" . $file->extension,
                'Content-Disposition' => 'attachment; filename="' . $student->last_name . "_" . $student->student_number . "_" . $file->type . "." . $file->extension,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to download authenticated student's file. " . $e->getMessage() . ".\n");
            return "Something went wrong.";
        }
    }
}
