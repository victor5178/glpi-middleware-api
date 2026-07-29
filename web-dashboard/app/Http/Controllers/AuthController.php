<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('glpi_user')) {
            return redirect()->route('dashboard');
        }
        return view('login');
    }

    public function login(Request $request, GlpiClient $glpi)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($glpi->attemptLogin($data['username'], $data['password'])) {
            $request->session()->regenerate();
            $request->session()->put('glpi_user', $data['username']);
            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('username'))
            ->with('error', $glpi->lastError ?? 'Login failed.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('glpi_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
