<?php

namespace App\Traits;

use App\Models\Administrator;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

trait AdminTrait {
    public function getAllAdmins() {
        Log::info("Entering AdminTrait getAllAdmins...");

        $users = [];

        $users = Administrator::where('first_name', '<>', null)
                              ->where('last_name', '<>', null)
                              ->where('email', '<>', null)
                              ->where('is_super_admin', '<>', true)
                              ->get();

        return $users;
    }

    public function getAllStudents() {
        Log::info("Entering AdminTrait getAllStudents...");

        $users = [];

        $users = Student::where('first_name', '<>', null)
                        ->where('last_name', '<>', null)
                        ->where('student_number', '<>', null)
                        ->where('email', '<>', null)
                        ->where('course', '<>', null)
                        ->where('year', '<>', null)
                        ->where('term', '<>', null)
                        ->get();

        return $users;
    }
}