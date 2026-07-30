<?php

namespace App\Http\Controllers\Master;

use App\Models\LegalEntity;
use App\Models\Outlet;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    public function index()
    {
        $items = Region::with('legalEntity')->orderBy('name')->paginate(20);
        return view('master.regions.index', compact('items'));
    }

    public function create()
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);
        return view('master.regions.form', [
            'item'          => new Region(),
            'legalEntities' => $legalEntities,
            'allOutlets'    => collect(),
            'linkedIds'     => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        Region::create($data);
        return redirect()->route('master.regions.index')->with('success', 'Regional berhasil dibuat.');
    }

    public function edit(Region $region)
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);
        $allOutlets    = Outlet::with('region:id,name')->orderBy('name')->get(['id', 'name', 'region_id']);
        $linkedIds     = $allOutlets->where('region_id', $region->id)->pluck('id')->toArray();
        return view('master.regions.form', [
            'item'          => $region,
            'legalEntities' => $legalEntities,
            'allOutlets'    => $allOutlets,
            'linkedIds'     => $linkedIds,
        ]);
    }

    public function update(Request $request, Region $region)
    {
        $data = $this->validated($request, $region->id);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $outletIds = array_values(array_filter(
            array_map('intval', $request->input('outlet_link_ids', [])),
            fn ($id) => $id > 0
        ));

        DB::transaction(function () use ($region, $data, $outletIds) {
            $region->update($data);
            // Lepas outlet yang dulu terhubung tapi sekarang tidak dicentang
            Outlet::where('region_id', $region->id)
                  ->when(!empty($outletIds), fn ($q) => $q->whereNotIn('id', $outletIds))
                  ->update(['region_id' => null]);
            // Hubungkan outlet yang dicentang (hanya yang belum punya region atau sudah milik region ini)
            if (!empty($outletIds)) {
                Outlet::whereIn('id', $outletIds)
                      ->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', $region->id))
                      ->update(['region_id' => $region->id]);
            }
        });

        return redirect()->route('master.regions.index')->with('success', 'Regional berhasil disimpan.');
    }

    public function destroy(Region $region)
    {
        $region->delete();
        return back()->with('success', 'Regional berhasil dihapus.');
    }

    public function toggle(Region $region)
    {
        $region->update(['is_active' => !$region->is_active]);
        $status = $region->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Regional '{$region->name}' berhasil {$status}.");
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'legal_entity_id'  => ['nullable', 'integer', 'exists:legal_entities,id'],
            'name'             => ['required', 'string', 'max:100'],
            'province'         => ['nullable', 'string', 'max:100'],
            'bpjs_area_code'   => ['nullable', 'string', 'max:20'],
            'umr_amount'       => ['nullable', 'numeric', 'min:0'],
            'umr_year'         => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_active'        => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string'],
        ]);
    }
}
