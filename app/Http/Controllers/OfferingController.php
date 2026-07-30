<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\GeneratedDocument;
use App\Models\OfferingTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OfferingController extends Controller
{
    public function templates()
    {
        abort_unless(Schema::hasTable('offering_templates'), 404, 'Table offering_templates belum ada. Jalankan migrasi offering module.');

        $items = OfferingTemplate::query()->orderByDesc('updated_at')->paginate(20);
        return view('offering.templates.index', compact('items'));
    }

    public function templatesCreate()
    {
        abort_unless(Schema::hasTable('offering_templates'), 404, 'Table offering_templates belum ada. Jalankan migrasi offering module.');

        return view('offering.templates.create');
    }

    public function templatesStore(Request $request)
    {
        abort_unless(Schema::hasTable('offering_templates'), 404, 'Table offering_templates belum ada. Jalankan migrasi offering module.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'content_html' => ['required', 'string'],
        ]);

        OfferingTemplate::create($data);
        return redirect()->route('hr.offering.templates')->with('success', 'Template disimpan.');
    }

    public function preview(Employee $employee)
    {
        abort_unless(Schema::hasTable('generated_documents'), 404, 'Table generated_documents belum ada.');

        $html = $this->renderOfferingHtml($employee);

        return view('offering.preview', [
            'employee' => $employee,
            'html' => $html,
        ]);
    }

    public function generate(Request $request, Employee $employee)
    {
        abort_unless(Schema::hasTable('generated_documents'), 404, 'Table generated_documents belum ada.');

        $html = $this->renderOfferingHtml($employee);
        $filePath = 'documents/offering/' . Str::uuid()->toString() . '.html';
        Storage::disk('public')->put($filePath, $html);

        $doc = GeneratedDocument::query()->create([
            'document_number' => 'OFF-' . now()->format('Ymd-His'),
            'employee_id' => $employee->id,
            'type' => 'offering',
            'file_path' => $filePath,
            'generated_at' => now(),
        ]);

        return redirect()->route('documents.download', $doc)->with('success', 'Offering letter berhasil dibuat.');
    }

    public function download(GeneratedDocument $generatedDocument)
    {
        $path = (string) ($generatedDocument->file_path ?? '');
        abort_if($path === '', 404, 'Dokumen belum tersedia.');
        abort_unless(Storage::disk('public')->exists($path), 404, 'File dokumen tidak ditemukan.');

        $absolutePath = Storage::disk('public')->path($path);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($extension === 'html') {
            return Response::make(file_get_contents($absolutePath), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return response()->file($absolutePath);
    }

    public function show(GeneratedDocument $doc)
    {
        $url = Storage::disk('public')->exists((string) $doc->file_path)
            ? Storage::disk('public')->url((string) $doc->file_path)
            : null;

        return view('offering.show', compact('doc', 'url'));
    }

    private function renderOfferingHtml(Employee $employee): string
    {
        $template = $this->resolveOfferingTemplate();

        return strtr($template, [
            '{TODAY}' => now()->format('d-m-Y'),
            '{FULL_NAME}' => (string) ($employee->full_name ?? '-'),
            '{NIK}' => (string) ($employee->nik ?? '-'),
            '{EMAIL}' => (string) ($employee->email_private ?? optional($employee->user)->email ?? '-'),
            '{ROLE}' => (string) ($employee->jabatan ?? optional($employee->position)->name ?? '-'),
            '{POSITION}' => (string) (optional($employee->position)->name ?? $employee->jabatan ?? '-'),
            '{DEPARTMENT}' => (string) (optional($employee->department)->name ?? '-'),
            '{JOIN_DATE}' => optional($employee->join_date)->format('d-m-Y') ?: '-',
            '{PROBATION_END_DATE}' => optional($employee->probation_end_date)->format('d-m-Y') ?: '-',
            '{STATUS_EMPLOYMENT}' => strtoupper((string) ($employee->status_employment ?? '-')),
        ]);
    }

    private function resolveOfferingTemplate(): string
    {
        if (Schema::hasTable('document_templates')) {
            $template = DocumentTemplate::query()
                ->where('type', 'offering')
                ->latest('id')
                ->value('content_html');

            if (is_string($template) && trim($template) !== '') {
                return $template;
            }
        }

        $fallback = resource_path('views/offering/default_template.html');
        if (is_file($fallback)) {
            return (string) file_get_contents($fallback);
        }

        return '<h1>Offering Letter</h1><p>{FULL_NAME}</p><p>{POSITION}</p><p>{DEPARTMENT}</p>';
    }
}
