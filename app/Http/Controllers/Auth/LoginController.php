<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the login page.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/Login', [
            'appName' => config('app.name', 'DiffOps'),
        ]);
    }
}
