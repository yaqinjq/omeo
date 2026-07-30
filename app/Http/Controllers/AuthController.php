<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $user = DB::table('users')->where('email', $data['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Email tidak ditemukan.'])->withInput();
        }

        if (!Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'Password salah.'])->withInput();
        }

        $role = 'applicant';
        if (Schema::hasColumn('users','role') && !empty($user->role)) {
            $role = $user->role;
        } elseif (Schema::hasColumn('users','is_admin') && (int)$user->is_admin === 1) {
            $role = 'admin';
        }

        $request->session()->put('auth_user', [
            'id' => $user->id,
            'name' => $user->name ?? ($user->email ?? 'User'),
            'email' => $user->email ?? null,
            'role' => $role,
        ]);

        return redirect()->route('dashboard');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255'],
            'password' => ['required','min:8','confirmed'],
        ]);

        $exists = DB::table('users')->where('email',$data['email'])->exists();
        if ($exists) {
            return back()->withErrors(['email' => 'Email sudah terdaftar.'])->withInput();
        }

        $insert = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('users','role')) {
            $insert['role'] = 'applicant';
        }

        DB::table('users')->insert($insert);

        return redirect()->route('login')->with('status','Registrasi berhasil. Silakan login.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('auth_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function setup(Request $request)
    {
        if (app()->environment('production')) abort(404);

        if (DB::table('users')->count() > 0) {
            return response()->view('auth.setup_done', [], 200);
        }

        $email = $request->query('email','admin@local.test');
        $pass  = $request->query('password','Password123!');
        $name  = $request->query('name','Admin HRD');

        $insert = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($pass),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('users','role')) $insert['role'] = 'admin';
        if (Schema::hasColumn('users','is_admin')) $insert['is_admin'] = 1;

        DB::table('users')->insert($insert);

        return response()->view('auth.setup_created', [
            'email' => $email,
            'password' => $pass,
        ], 200);
    }
}

