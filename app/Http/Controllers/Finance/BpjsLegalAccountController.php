<?php

namespace App\Http\Controllers\Finance;

use App\Models\BpjsLegalAccount;
use App\Models\LegalEntity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BpjsLegalAccountController extends Controller
{
    public function index()
    {
        $items = BpjsLegalAccount::with('legalEntity')
            ->orderBy('bpjs_type')
            ->paginate(25);
        return view('finance.bpjs_legal.index', compact('items'));
    }

    public function create()
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);
        return view('finance.bpjs_legal.form', ['item' => new BpjsLegalAccount(), 'legalEntities' => $legalEntities]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        BpjsLegalAccount::create($data);
        return redirect()->route('finance.bpjs-legal.index')->with('success', 'Akun BPJS legal berhasil disimpan.');
    }

    public function edit(BpjsLegalAccount $bpjsLegal)
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);
        return view('finance.bpjs_legal.form', ['item' => $bpjsLegal, 'legalEntities' => $legalEntities]);
    }

    public function update(Request $request, BpjsLegalAccount $bpjsLegal)
    {
        $data = $this->validated($request, $bpjsLegal->id);
        $bpjsLegal->update($data);
        return redirect()->route('finance.bpjs-legal.index')->with('success', 'Akun BPJS legal berhasil diperbarui.');
    }

    public function destroy(BpjsLegalAccount $bpjsLegal)
    {
        $bpjsLegal->delete();
        return back()->with('success', 'Akun BPJS legal berhasil dihapus.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'legal_entity_id' => ['required', 'integer', 'exists:legal_entities,id'],
            'bpjs_type'       => ['required', 'in:ketenagakerjaan,kesehatan'],
            'account_number'         => ['required', 'string', 'max:50'],
            'npp'                    => ['nullable', 'string', 'max:20'],
            'upah_basis_perhitungan' => ['nullable', 'numeric', 'min:0'],
            'account_name'           => ['nullable', 'string', 'max:200'],
            'bpjs_branch'     => ['nullable', 'string', 'max:100'],
            'registered_date' => ['nullable', 'date'],
            'is_active'       => ['boolean'],
            'notes'           => ['nullable', 'string'],
        ]);
    }
}
