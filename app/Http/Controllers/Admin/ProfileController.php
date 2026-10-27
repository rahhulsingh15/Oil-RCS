<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Change own password */
class ProfileController extends Controller
{
    public function editPassword()
    {
        return view('admin.change-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);
        AuditLog::record('updated', $user, [], ['password' => '(changed)'], "{$user->name} changed their password");

        return redirect()->route('profile.password')->with('success', 'Password changed.');
    }
}
