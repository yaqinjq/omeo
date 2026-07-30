<?php

namespace App\Http\Controllers\Finance;

use App\Models\DepartmentCategoryMapping;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class DepartmentCategoryMappingController extends Controller
{
    public function index()
    {
        $items = DepartmentCategoryMapping::orderBy('match_type')->orderBy('match_value')->paginate(30);
        return view('finance.department_mappings.index', compact('items'));
    }

    public function create()
    {
        return view('finance.department_mappings.form', [
            'item' => new DepartmentCategoryMapping(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        DepartmentCategoryMapping::create($data);
        return redirect()->route('finance.department-mappings.index')->with('success', 'Mapping berhasil ditambahkan.');
    }

    public function edit(DepartmentCategoryMapping $departmentMapping)
    {
        return view('finance.department_mappings.form', [
            'item' => $departmentMapping,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, DepartmentCategoryMapping $departmentMapping)
    {
        $data = $this->validated($request, $departmentMapping->id);
        $data['is_active'] = $request->has('is_active') ? 1 : 0;
        $departmentMapping->update($data);
        return redirect()->route('finance.department-mappings.index')->with('success', 'Mapping berhasil diperbarui.');
    }

    public function destroy(DepartmentCategoryMapping $departmentMapping)
    {
        $departmentMapping->delete();
        return back()->with('success', 'Mapping berhasil dihapus.');
    }

    public function toggle(DepartmentCategoryMapping $departmentMapping)
    {
        $departmentMapping->update(['is_active' => !$departmentMapping->is_active]);
        $status = $departmentMapping->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Mapping '{$departmentMapping->match_value}' berhasil {$status}.");
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'match_type'  => ['required', 'in:sub_dept,position'],
            'match_value' => [
                'required', 'string', 'max:150',
                Rule::unique('department_category_mappings')
                    ->where('match_type', $request->input('match_type'))
                    ->ignore($id),
            ],
            'category'    => ['required', 'in:bar,kitchen,service,office,prive,lainnya'],
            'notes'       => ['nullable', 'string'],
        ], [
            'match_type.required'  => 'Tipe wajib dipilih.',
            'match_value.required' => 'Nilai wajib diisi.',
            'match_value.unique'   => 'Kombinasi tipe + nilai sudah ada.',
            'category.required'    => 'Kategori wajib dipilih.',
        ]);
    }
}
