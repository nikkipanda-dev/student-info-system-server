<?php

namespace App\Traits;

use App\Models\Administrator;
use Illuminate\Support\Facades\Log;

trait AdminTrait {
    public function getAllAdmins() {
        Log::info("Entering AdminTrait getAll...");

        $users = [];

        $users = Administrator::where('first_name', '<>', null)
                              ->where('last_name', '<>', null)
                              ->where('email', '<>', null)
                              ->where('is_super_admin', '<>', true)
                              ->get();

        return $users;
    }
}