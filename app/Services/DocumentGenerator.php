<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DocumentGenerator
{
    /**
     * Generate PDF dari template HTML di DB.
     * Variabel sederhana: {FULL_NAME}, {NIK}, {JOIN_DATE}, {TODAY}
     */
    public function generateOffering(Employee $employee): GeneratedDocument
    {
        $tpl = DocumentTemplate::where('type', 'offering')->latest('id')->firstOrFail();

        $html = str_replace(
            ['{FULL_NAME}', '{NIK}', '{JOIN_DATE}', '{TODAY}'],
            [
                e($employee->full_name),
                e($employee->nik),
                optional($employee->join_date)->format('d-m-Y'),
                Carbon::now()->format('d-m-Y'),
            ],
            $tpl->content_html
        );

        $pdf = Pdf::loadHTML($html);

        $fileName = 'documents/offering/' . Str::uuid()->toString() . '.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        return GeneratedDocument::create([
            'document_number' => $this->simpleDocNumber('OFF'),
            'employee_id' => $employee->id,
            'type' => 'offering',
            'file_path' => $fileName,
            'generated_at' => Carbon::now(),
        ]);
    }

    protected function simpleDocNumber(string $prefix): string
    {
        // Versi sederhana (nanti bisa diganti pakai document_numbering_formats)
        return $prefix . '-' . Carbon::now()->format('Ymd-His');
    }
}
