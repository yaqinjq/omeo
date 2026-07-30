<?php

namespace App\Http\Controllers\Hrd;

use App\Exports\IqTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\AssessmentForm;
use App\Services\IqQuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ChoiceImportController extends Controller
{
    public function __construct(private readonly IqQuestionImportService $importService)
    {
    }

    public function index(string $type)
    {
        $this->assertTypeSupported($type);

        $forms = Schema::hasTable('forms')
            ? AssessmentForm::query()
                ->where('type', $type)
                ->orderByDesc('id')
                ->get(['id', 'name', 'duration_minutes', 'is_active'])
            : collect();

        $templateHeaders = $this->importService->templateHeaders();
        $typeLabel = AssessmentForm::labelFor($type);
        $moduleWarning = Schema::hasTable('forms') ? null : 'Schema Form Dinamis belum lengkap di environment ini. Import ' . $typeLabel . ' dibuka dalam mode aman.';

        return view('hrd.forms.import_choice', compact('forms', 'templateHeaders', 'type', 'typeLabel', 'moduleWarning'));
    }

    public function downloadCsv(string $type)
    {
        $this->assertTypeSupported($type);

        $headers = $this->importService->templateHeaders();
        $rows = $this->importService->templateSampleRows();

        $callback = static function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        };

        return Response::streamDownload($callback, 'template-import-soal-' . $type . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadXlsx(string $type)
    {
        $this->assertTypeSupported($type);

        return Excel::download(
            new IqTemplateExport(
                $this->importService->templateHeadersIndonesia(),
                $this->importService->templateSampleRows(),
            ),
            'template-import-soal-' . $type . '.xlsx'
        );
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertTypeSupported($type);

        if (! Schema::hasTable('forms')) {
            return back()->withInput()->with('error', 'Schema Form Dinamis belum siap. Jalankan migrasi forms terlebih dahulu.');
        }

        $validated = $request->validate([
            'target_mode' => ['required', Rule::in(['create', 'append'])],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'form_name' => ['required_if:target_mode,create', 'nullable', 'string', 'max:255'],
            'duration_minutes' => ['required_if:target_mode,create', 'nullable', 'integer', 'min:1', 'max:300'],
            'form_id' => [
                'required_if:target_mode,append',
                'nullable',
                'integer',
                Rule::exists('forms', 'id')->where(fn ($query) => $query->where('type', $type)),
            ],
        ]);

        try {
            $result = $this->importService->import(
                $validated['file'],
                $validated,
                (int) $request->user()->id,
                $type,
            );
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Import gagal diproses.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Terjadi kendala saat import. Silakan coba lagi.');
        }

        $summary = [
            'form_id' => $result['form']->id,
            'form_name' => $result['form']->name,
            'imported_questions' => $result['imported_questions'],
            'imported_options' => $result['imported_options'],
            'skipped_rows' => $result['skipped_rows'],
            'row_errors' => $result['row_errors'],
            'warnings' => $result['warnings'] ?? [],
        ];

        $flashType = $result['imported_questions'] > 0 ? 'success' : 'error';
        $flashMessage = $result['imported_questions'] > 0
            ? 'Import bank soal ' . AssessmentForm::labelFor($type) . ' selesai diproses.'
            : 'Tidak ada baris valid yang berhasil diimport.';

        return redirect()
            ->route('hrd.import.choice.index', ['type' => $type])
            ->with($flashType, $flashMessage)
            ->with('import_summary', $summary);
    }

    private function assertTypeSupported(string $type): void
    {
        abort_unless(array_key_exists($type, AssessmentForm::importableChoiceTypes()), 404);
    }
}


