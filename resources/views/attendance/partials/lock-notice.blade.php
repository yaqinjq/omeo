<section class="rounded-[2rem] border border-amber-300 bg-amber-50 p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-lg font-bold text-amber-950">{{ $attendanceLockTitle }}</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-amber-900">{{ data_get($attendanceEligibility, 'message') }}</p>
            @if($missingProfileSections !== [])
                <div class="mt-3 rounded-2xl border border-amber-200 bg-white/70 px-4 py-3 text-sm text-amber-900">
                    Bagian yang masih perlu dilengkapi: <strong>{{ collect($missingProfileSections)->map(fn ($section) => str($section)->replace('_', ' ')->title())->implode(', ') }}</strong>
                </div>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('employee-profile.show') }}" class="rounded-2xl border border-amber-400 bg-white px-4 py-3 text-sm font-semibold text-amber-950 hover:bg-amber-100">Buka Profil Saya</a>
            @if(data_get($attendanceEligibility, 'requires_payroll'))
                <a href="{{ route('probation-onboarding.edit') }}" class="rounded-2xl bg-amber-900 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-800">Lengkapi Payroll</a>
            @endif
        </div>
    </div>
</section>
