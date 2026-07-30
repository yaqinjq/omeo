<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password', [
            'mailConfigured' => MailConfiguration::isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        MailConfiguration::applyFromSettings();

        if (! MailConfiguration::isConfigured()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Reset password belum bisa dikirim karena email server belum dikonfigurasi. Hubungi HRD / admin.']);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Terjadi kendala saat mengirim email reset password. Silakan coba lagi atau hubungi HRD / admin.']);
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Link reset password sudah dikirim ke email Anda. Silakan cek inbox dan spam.')
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}
