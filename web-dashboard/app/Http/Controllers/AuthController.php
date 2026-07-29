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

        $token = $glpi->attemptLogin($data['username'], $data['password']);
        if ($token !== null) {
            $request->session()->regenerate();
            $request->session()->put('glpi_user', $data['username']);
            // Reused for GLPI searches so no service account is needed.
            $request->session()->put('glpi_session_token', $token);
            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('username'))
            ->with('error', $glpi->lastError ?? 'Login failed.');
    }

    public function logout(Request $request, GlpiClient $glpi)
    {
        $token = $request->session()->get('glpi_session_token');
        if ($token) {
            $glpi->endSession($token); // best-effort close on GLPI
        }
        $request->session()->forget(['glpi_user', 'glpi_session_token']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
