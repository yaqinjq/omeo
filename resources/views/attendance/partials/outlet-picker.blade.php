<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <form method="GET" class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Pilih outlet hari ini</label>
            <select name="outlet_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">-- Pilih outlet --</option>
                @foreach($availableOutlets as $o)
                    <option value="{{ $o->id }}" @selected((string) request('outlet_id') === (string) $o->id)>{{ $o->name }} ({{ $o->timezone ?: 'Asia/Jakarta' }})</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Set Outlet</button>
    </form>
</section>
