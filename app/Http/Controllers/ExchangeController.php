<?php

namespace App\Http\Controllers;

class ExchangeController extends Controller
{
    public function index()
    {
        return inertia('Exchange');
    }
}
