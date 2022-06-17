<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function authenticate() {
        return response('ok', 200);
    }

    public function test(Request $request) {
        return response('test ok', 200);
    }
}
