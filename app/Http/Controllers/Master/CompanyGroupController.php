<?php

namespace App\Http\Controllers\Master;

use App\Models\CompanyGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CompanyGroupController extends Controller
{
    public function index()
    {
        $groups = CompanyGroup::withCount('legalEntities')
            ->orderBy('name')
            ->paginate(20);

        return view('master.company_groups.index', compact('groups'));
    }

    public function create()
    {
        return view('master.company_groups.form', [
            'group' => new CompanyGroup(),
            'mode'  => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200', Rule::unique('company_groups', 'name')],
            'short_name'        => $request->filled('short_name')
                ? ['nullable', 'string', 'max:50', Rule::unique('company_groups', 'short_name')]
                : ['nullable', 'string', 'max:50'],
            'npwp'              => 'nullable|string|max:30',
            'address'           => 'nullable|string|max:500',
            'headquarters_city' => 'nullable|string|max:100',
            'website'           => 'nullable|string|max:255',
            'is_active'         => 'nullable|boolean',
        ], [
            'name.required' => 'Nama group perusahaan wajib diisi.',
            'name.max'      => 'Nama maksimal 200 karakter.',
            'name.unique'   => 'Nama group perusahaan sudah digunakan.',
            'short_name.unique' => 'Nama singkat sudah digunakan oleh group lain.',
        ]);

        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        CompanyGroup::create($data);

        return redirect()->route('master.company-groups.index')
            ->with('success', 'Group perusahaan berhasil ditambahkan.');
    }

    public function edit(CompanyGroup $companyGroup)
    {
        return view('master.company_groups.form', [
            'group' => $companyGroup,
            'mode'  => 'edit',
        ]);
    }

    public function update(Request $request, CompanyGroup $companyGroup)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:200', Rule::unique('company_groups', 'name')->ignore($companyGroup->id)],
            'short_name'        => $request->filled('short_name')
                ? ['nullable', 'string', 'max:50', Rule::unique('company_groups', 'short_name')->ignore($companyGroup->id)]
                : ['nullable', 'string', 'max:50'],
            'npwp'              => 'nullable|string|max:30',
            'address'           => 'nullable|string|max:500',
            'headquarters_city' => 'nullable|string|max:100',
            'website'           => 'nullable|string|max:255',
            'is_active'         => 'nullable|boolean',
        ], [
            'name.required' => 'Nama group perusahaan wajib diisi.',
            'name.unique'   => 'Nama group perusahaan sudah digunakan.',
            'short_name.unique' => 'Nama singkat sudah digunakan oleh group lain.',
        ]);

        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        $companyGroup->update($data);

        return redirect()->route('master.company-groups.index')
            ->with('success', 'Group perusahaan berhasil diperbarui.');
    }

    public function destroy(CompanyGroup $companyGroup)
    {
        $companyGroup->delete();
        return back()->with('success', 'Group perusahaan berhasil dihapus.');
    }

    public function toggle(CompanyGroup $companyGroup)
    {
        $companyGroup->update(['is_active' => !$companyGroup->is_active]);

        $status = $companyGroup->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Group '{$companyGroup->name}' berhasil {$status}.");
    }

    public function importForm()
    {
        return view('master.company_groups.import');
    }

    public function importStore(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'import_file.required' => 'File import wajib dipilih.',
            'import_file.mimes'    => 'File harus berformat XLSX, XLS, atau CSV.',
        ]);

        $file = $request->file('import_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $rows = match ($ext) {
            'csv'   => $this->parseCsvImport($file->getRealPath()),
            default => $this->parseExcelImport($file->getRealPath()),
        };

        $inserted = 0;
        $skipped  = 0;
        foreach ($rows as $row) {
            if (empty(trim($row['name'] ?? ''))) continue;
            if (CompanyGroup::where('name', trim($row['name']))->exists()) {
                $skipped++;
                continue;
            }
            CompanyGroup::create([
                'name'              => trim($row['name']),
                'short_name'        => trim($row['short_name'] ?? '') ?: null,
                'npwp'              => trim($row['npwp'] ?? '') ?: null,
                'address'           => trim($row['address'] ?? '') ?: null,
                'headquarters_city' => trim($row['headquarters_city'] ?? '') ?: null,
                'website'           => trim($row['website'] ?? '') ?: null,
                'is_active'         => true,
            ]);
            $inserted++;
        }

        return redirect()->route('master.company-groups.index')
            ->with('success', "{$inserted} group berhasil diimport, {$skipped} dilewati (sudah ada).");
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_company_groups.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8
            fputcsv($handle, ['name', 'short_name', 'npwp', 'address', 'headquarters_city', 'website']);
            fputcsv($handle, ['PT Makmur Ramenjaya Mandiri', 'MKO', '12.345.678.9-012.345', 'Jl. Raya No. 1, Malang', 'Malang', 'https://mko.co.id']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function parseCsvImport(string $path): array
    {
        $rows    = [];
        $headers = [];
        $handle  = fopen($path, 'r');
        $i = 0;

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($i === 0) {
                $headers = array_map('strtolower', array_map('trim', $line));
                $i++;
                continue;
            }
            $rows[] = array_combine($headers, array_pad($line, count($headers), ''));
            $i++;
        }

        fclose($handle);
        return $rows;
    }

    private function parseExcelImport(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];
        $headers     = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = trim((string) ($cell->getValue() ?? ''));
            }
            if ($rowIndex === 1) {
                $headers = array_map('strtolower', $cells);
                continue;
            }
            if (empty(array_filter($cells))) continue;
            $rows[] = array_combine($headers, array_pad($cells, count($headers), ''));
        }

        return $rows;
    }
}
