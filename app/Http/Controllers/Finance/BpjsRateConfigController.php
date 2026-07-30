<?php

namespace App\Http\Controllers\Finance;

use App\Models\BpjsRateConfig;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BpjsRateConfigController extends Controller
{
    public function index()
    {
        $items = BpjsRateConfig::with('region.legalEntity')
            ->orderByDesc('periode_mulai')
            ->paginate(25);
        return view('finance.bpjs_rates.index', compact('items'));
    }

    public function create()
    {
        $regions = Region::with('legalEntity')->where('is_active', true)->orderBy('name')->get();
        return view('finance.bpjs_rates.form', ['item' => new BpjsRateConfig(), 'regions' => $regions]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        BpjsRateConfig::create($data);
        return redirect()->route('finance.bpjs-rates.index')->with('success', 'Konfigurasi tarif BPJS berhasil disimpan.');
    }

    public function edit(BpjsRateConfig $bpjsRate)
    {
        $regions = Region::with('legalEntity')->where('is_active', true)->orderBy('name')->get();
        return view('finance.bpjs_rates.form', ['item' => $bpjsRate, 'regions' => $regions]);
    }

    public function update(Request $request, BpjsRateConfig $bpjsRate)
    {
        $data = $this->validated($request);
        $bpjsRate->update($data);
        return redirect()->route('finance.bpjs-rates.index')->with('success', 'Konfigurasi tarif BPJS berhasil diperbarui.');
    }

    public function destroy(BpjsRateConfig $bpjsRate)
    {
        $bpjsRate->delete();
        return back()->with('success', 'Konfigurasi tarif BPJS berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'region_id'             => ['nullable', 'integer', 'exists:regions,id'],
            'periode_mulai'         => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'periode_selesai'       => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'jkk_rate'              => ['required', 'numeric', 'min:0', 'max:1'],
            'jkm_rate'              => ['required', 'numeric', 'min:0', 'max:1'],
            'jht_employer_rate'     => ['required', 'numeric', 'min:0', 'max:1'],
            'jht_employee_rate'     => ['required', 'numeric', 'min:0', 'max:1'],
            'jp_employer_rate'      => ['required', 'numeric', 'min:0', 'max:1'],
            'jp_employee_rate'      => ['required', 'numeric', 'min:0', 'max:1'],
            'bpjskes_employer_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'bpjskes_employee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'bpjskes_salary_cap'    => ['nullable', 'numeric', 'min:0'],
            'basis_perhitungan'     => ['nullable', 'in:umr,gaji_aktual,capped_umr'],
            'is_active'             => ['boolean'],
            'notes'                 => ['nullable', 'string'],
        ]);
    }
}
