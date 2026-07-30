<?php

namespace App\Http\Controllers\Master;

use App\Models\CompanyGroup;
use App\Models\LegalEntity;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LegalEntityController extends Controller
{
    public function index()
    {
        $items = LegalEntity::with('companyGroup')->orderBy('name')->paginate(20);
        return view('master.legal_entities.index', compact('items'));
    }

    public function create()
    {
        $companyGroups = CompanyGroup::orderBy('name')->get(['id', 'name']);
        return view('master.legal_entities.form', [
            'item'          => new LegalEntity(),
            'companyGroups' => $companyGroups,
            'outlets'       => collect(),
            'linkedIds'     => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        LegalEntity::create($data);
        return redirect()->route('master.legal-entities.index')->with('success', 'Entitas legal berhasil dibuat.');
    }

    public function edit(LegalEntity $legalEntity)
    {
        $companyGroups = CompanyGroup::orderBy('name')->get(['id', 'name']);
        $outlets       = Outlet::orderBy('name')->get(['id', 'name', 'legal_entity_id']);
        $linkedIds     = $outlets->where('legal_entity_id', $legalEntity->id)->pluck('id')->toArray();
        return view('master.legal_entities.form', [
            'item'          => $legalEntity,
            'companyGroups' => $companyGroups,
            'outlets'       => $outlets,
            'linkedIds'     => $linkedIds,
        ]);
    }

    public function update(Request $request, LegalEntity $legalEntity)
    {
        $data = $this->validated($request, $legalEntity->id);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $outletIds = array_values(array_filter(
            array_map('intval', $request->input('outlet_link_ids', [])),
            fn ($id) => $id > 0
        ));

        DB::transaction(function () use ($legalEntity, $data, $outletIds) {
            $legalEntity->update($data);
            Outlet::where('legal_entity_id', $legalEntity->id)
                  ->when(!empty($outletIds), fn ($q) => $q->whereNotIn('id', $outletIds))
                  ->update(['legal_entity_id' => null]);
            if (!empty($outletIds)) {
                Outlet::whereIn('id', $outletIds)->update(['legal_entity_id' => $legalEntity->id]);
            }
        });

        return redirect()->route('master.legal-entities.index')->with('success', 'Entitas legal berhasil disimpan.');
    }

    public function destroy(LegalEntity $legalEntity)
    {
        $legalEntity->delete();
        return back()->with('success', 'Entitas legal berhasil dihapus.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'company_group_id' => ['nullable', 'integer', 'exists:company_groups,id'],
            'name'             => ['required', 'string', 'max:200', Rule::unique('legal_entities', 'name')->ignore($id)],
            'short_name'       => $request->filled('short_name')
                ? ['nullable', 'string', 'max:50', Rule::unique('legal_entities', 'short_name')->ignore($id)]
                : ['nullable', 'string', 'max:50'],
            'entity_type'      => ['required', 'in:PT,CV,UD,Firma,Yayasan,Lainnya'],
            'npwp'             => ['nullable', 'string', 'max:30'],
            'nib'              => ['nullable', 'string', 'max:30'],
            'address'          => ['nullable', 'string', 'max:500'],
            'city'             => ['nullable', 'string', 'max:100'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:150'],
            'director_name'    => ['nullable', 'string', 'max:150'],
            'notes'            => ['nullable', 'string'],
        ], [
            'name.required'     => 'Nama entitas wajib diisi.',
            'name.unique'       => 'Nama entitas sudah digunakan oleh entitas lain.',
            'short_name.unique' => 'Nama singkat sudah digunakan oleh entitas lain.',
        ]);
    }
}
