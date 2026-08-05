<?php

namespace App\Http\Controllers\Master;

use App\Models\MasterShift;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class MasterShiftController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->integer('outlet_id') ?: null;

        $shifts = MasterShift::query()
            ->with('outlet:id,name,outlet_type')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderBy('outlet_id')
            ->orderBy('in_time')
            ->get();

        $outlets = Outlet::operational()->orderBy('name')->get(['id', 'name']);

        return view('master.shifts.index', compact('shifts', 'outlets', 'outletId'));
    }

    public function create()
    {
        $outlets = Outlet::operational()->orderBy('name')->get(['id', 'name']);
        return view('master.shifts.create', compact('outlets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        MasterShift::create($data);

        return redirect()->route('master-shifts.index', ['outlet_id' => $data['outlet_id']])
            ->with('success', 'Shift berhasil ditambahkan.');
    }

    public function edit(MasterShift $master_shift)
    {
        $outlets = Outlet::operational()->orderBy('name')->get(['id', 'name']);
        return view('master.shifts.edit', ['shift' => $master_shift, 'outlets' => $outlets]);
    }

    public function update(Request $request, MasterShift $master_shift): RedirectResponse
    {
        $master_shift->update($this->validated($request, $master_shift->id));

        return redirect()->route('master-shifts.index', ['outlet_id' => $master_shift->outlet_id])
            ->with('success', 'Shift berhasil diupdate.');
    }

    public function destroy(MasterShift $master_shift): RedirectResponse
    {
        $outletId = $master_shift->outlet_id;

        $usedCount = \Illuminate\Support\Facades\DB::table('attendance_schedules')
            ->where('master_shift_id', $master_shift->id)
            ->count();

        if ($usedCount > 0) {
            return back()->with('error', "Shift ini masih dipakai di {$usedCount} jadwal presensi — nonaktifkan saja (jangan hapus) supaya histori presensi lama tidak kehilangan referensi jamnya.");
        }

        $master_shift->delete();

        return redirect()->route('master-shifts.index', ['outlet_id' => $outletId])
            ->with('success', 'Shift berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'code'      => [
                'required', 'string', 'max:50',
                Rule::unique('master_shifts', 'code')->where(fn ($q) => $q->where('outlet_id', $request->integer('outlet_id')))->ignore($ignoreId),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'in_time'   => ['required', 'date_format:H:i'],
            'out_time'  => ['required', 'date_format:H:i'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
