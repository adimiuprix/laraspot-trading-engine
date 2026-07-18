<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class YussController extends Controller
{
    public function index(Request $request)
    {
        $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login success',
            'user' => Auth::user(),
        ]);
    }
}
