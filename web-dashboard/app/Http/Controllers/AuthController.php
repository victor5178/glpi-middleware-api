<?php

namespace App\Http\Controllers;

use App\Services\GlpiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

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

        $token = $glpi->attemptLogin($data['username'], $data['password']);
        if ($token !== null) {
            $glpi->endSession($token); // close the login session; searches re-auth
            $request->session()->regenerate();
            $request->session()->put('glpi_user', $data['username']);
            // Stored encrypted so each search can re-authenticate to GLPI
            // (no stale session tokens, no service account needed).
            $request->session()->put('glpi_pw', Crypt::encryptString($data['password']));
            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('username'))
            ->with('error', $glpi->lastError ?? 'Login failed.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['glpi_user', 'glpi_pw']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
