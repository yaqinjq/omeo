<?php

namespace App\Http\Controllers\Master;

use App\Exports\IqTemplateExport;
use App\Models\Department;
use App\Support\ImportHeaderValidator;
use App\Support\ImportTemplateColumn;
use App\Support\ImportTemplateSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DepartmentController extends Controller
{
    public function __construct(private readonly ImportHeaderValidator $headerValidator)
    {
    }

    public function index()
    {
        $items = Department::query()->orderBy('name')->paginate(15);
        return view('master.departments.index', compact('items'));
    }

    public function create()
    {
        return view('master.departments.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Department::create($data);
        return redirect()->route('departments.index')->with('success','Berhasil dibuat.');
    }

    public function edit(Department $department)
    {
        return view('master.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $data = $this->validated($request, $department->id);
        $department->update($data);
        return redirect()->route('departments.index')->with('success','Berhasil disimpan.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success','Berhasil dihapus.');
    }

    public function export()
    {
        $rows = Department::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Department $department) => [$department->code, $department->name])
            ->all();

        return Excel::download(
            new IqTemplateExport($this->schema()->exportHeaders(), $rows),
            'master-departemen-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function template()
    {
        return Excel::download(
            new IqTemplateExport($this->schema()->exportHeaders(), [['HRD', 'Human Resource']]),
            'template-import-departemen.xlsx'
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        try {
            $summary = $this->processImport($validated['file']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('departments.index')
            ->with($summary['created'] > 0 || $summary['updated'] > 0 ? 'success' : 'warning', 'Import master departemen selesai diproses.')
            ->with('import_summary', $summary);
    }

    private function processImport(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $match = $this->matchHeaders($rows);

        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0, 'row_errors' => [], 'warnings' => $match->warnings()];
        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $match->mapRow($rawRow);
            if ($this->isEmptyRow(array_values($row))) {
                continue;
            }

            $code = Str::upper(trim((string) ($row['code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '') {
                $summary['failed']++;
                $summary['row_errors'][] = ['row' => $rowNumber, 'message' => 'Code dan nama wajib diisi.'];
                continue;
            }

            $existing = Department::withTrashed()->where('code', $code)->first();
            if ($existing) {
                if (method_exists($existing, 'trashed') && $existing->trashed()) {
                    $existing->restore();
                }
                $existing->update(['name' => $name]);
                $summary['updated']++;
                continue;
            }

            Department::query()->create(['code' => $code, 'name' => $name]);
            $summary['created']++;
        }

        return $summary;
    }

    private function schema(): ImportTemplateSchema
    {
        return ImportTemplateSchema::make([
            ImportTemplateColumn::make('code', true, ['kode']),
            ImportTemplateColumn::make('name', true, ['nama']),
        ]);
    }

    private function matchHeaders(array $rows): \App\Support\ImportHeaderMatchResult
    {
        $match = $this->headerValidator->match($rows[0] ?? [], $this->schema());
        $this->headerValidator->ensureValid($match, 'departemen');

        return $match;
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required','string','max:100', Rule::unique('departments', 'name')->ignore($id)],
            'code' => ['required','string','max:10', Rule::unique('departments', 'code')->ignore($id)],
        ]);

        $data['code'] = Str::upper(trim((string) $data['code']));
        $data['name'] = trim((string) $data['name']);

        return $data;
    }

    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $rows = $sheets[0] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['file' => 'File import kosong.']);
        }

        return $rows;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
