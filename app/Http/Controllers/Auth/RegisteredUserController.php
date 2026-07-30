<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\User;
use App\Rules\BlacklistNikRule;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'string', 'max:50', new BlacklistNikRule(app(\App\Services\CandidateBlacklistService::class))],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        ApplicantProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'personal_json' => [
                    'full_name' => $request->name,
                    'email' => $request->email,
                    'ktp_number' => $request->nik,
                ],
            ]
        );

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('application-form.edit');
    }
}
