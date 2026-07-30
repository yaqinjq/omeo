<x-guest-layout>
    <div class="space-y-6">
        <div class="rounded-3xl border border-amber-200/80 bg-amber-50/90 px-4 py-4 text-sm text-amber-900 shadow-sm">
            <div class="flex items-start gap-3">
                <button type="button" class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-300 bg-white/80 text-amber-700" aria-label="Info password baru">
                    i
                </button>
                <div>
                    <div class="font-semibold">Panduan password baru</div>
                    <p class="mt-1 leading-6">Buat password baru yang mudah Anda ingat tetapi tidak mudah ditebak. Gunakan tombol lihat password untuk memastikan input sudah benar.</p>
                </div>
            </div>
        </div>

        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Reset Password</h1>
            <p class="mt-2 text-sm text-muted">Masukkan email login Anda dan password baru untuk menyelesaikan proses reset.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-200">Email</label>
                <input id="email" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-200">Password Baru</label>
                    <button type="button" class="text-xs font-semibold text-amber-700 underline underline-offset-2" data-toggle-password data-target="resetPassword">Lihat password</button>
                </div>
                <input id="resetPassword" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label for="password_confirmation" class="text-sm font-medium text-slate-700 dark:text-slate-200">Ulangi Password Baru</label>
                    <button type="button" class="text-xs font-semibold text-amber-700 underline underline-offset-2" data-toggle-password data-target="resetPasswordConfirmation">Lihat password</button>
                </div>
                <input id="resetPasswordConfirmation" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-400/40 dark:border-slate-700 dark:bg-slate-900/60" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end pt-2">
                <button class="btn-primary" type="submit">Reset Password</button>
            </div>
        </form>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.target);
                if (!target) return;

                const nextType = target.type === 'password' ? 'text' : 'password';
                target.type = nextType;
                button.textContent = nextType === 'password' ? 'Lihat password' : 'Sembunyikan password';
            });
        });
    </script>
</x-guest-layout>
