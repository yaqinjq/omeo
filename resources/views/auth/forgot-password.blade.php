<x-guest-layout>
    <div class="space-y-6">
        <div class="rounded-3xl border border-amber-200/80 bg-amber-50/90 px-4 py-4 text-sm text-amber-900 shadow-sm">
            <div class="flex items-start gap-3">
                <button type="button" class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-300 bg-white/80 text-amber-700" aria-label="Info reset password">
                    i
                </button>
                <div>
                    <div class="font-semibold">Panduan lupa password</div>
                    <p class="mt-1 leading-6">Masukkan email login yang aktif. Sistem akan mengirim link reset password agar Anda bisa membuat password baru sendiri tanpa bantuan tim IT.</p>
                </div>
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Lupa Password</h1>
            <p class="mt-2 text-sm text-muted">Masukkan email login Anda. Silakan cek inbox dan folder spam setelah link dikirim.</p>
        </div>

        @if (!($mailConfigured ?? false))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Email server belum terdeteksi aktif. Jika link reset tidak terkirim, tim admin perlu menyiapkan SMTP terlebih dahulu.
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-200">Email Login</label>
                <input id="email" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@email.com">
            </div>

            <button class="btn-primary w-full justify-center" type="submit">Kirim Link Reset Password</button>
        </form>

        <div class="text-center text-sm text-muted">
            Sudah ingat password? <a href="{{ route('login') }}" class="font-semibold text-brand underline">Kembali ke login</a>
        </div>
    </div>
</x-guest-layout>
