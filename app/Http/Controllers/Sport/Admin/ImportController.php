<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\Event;
use App\Models\Sport\ImportBatch;
use App\Services\Sport\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Import historis Excel/CSV — HANYA Admin Finance. Lihat
 * App\Services\Sport\ImportService untuk logika parsing/matching/commit dan
 * batasan yang dijaga (tidak ada API Zoho otomatis, tidak match nama saja,
 * idempotent, tidak menyentuh reward).
 */
class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService)
    {
    }

    public function create(): View
    {
        $events = Event::orderByDesc('event_date')->get(['id', 'sport', 'title', 'event_date']);

        return view('sport.admin.import.create', ['events' => $events]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sport_event_id' => ['required', 'integer', 'exists:sport_events,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $alreadyCommitted = ImportBatch::where('sport_event_id', $data['sport_event_id'])
            ->where('file_hash', $hash)
            ->where('status', ImportBatch::STATUS_COMMITTED)
            ->exists();

        if ($alreadyCommitted) {
            return back()->with('error', 'File yang identik sudah pernah diimport dan dikonfirmasi untuk event ini. Tidak diproses ulang.');
        }

        $batch = ImportBatch::create([
            'sport_event_id' => $data['sport_event_id'],
            'uploaded_by_user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'status' => ImportBatch::STATUS_UPLOADED,
        ]);

        $this->importService->parseAndStoreRows($batch, $file);

        return redirect()->route('sport.admin.import.map', $batch)->with('success', 'File diupload. Silakan petakan kolom.');
    }

    public function editMapping(ImportBatch $batch): View
    {
        $headers = $this->importService->availableHeaders($batch);

        return view('sport.admin.import.map', ['batch' => $batch, 'headers' => $headers]);
    }

    public function saveMapping(Request $request, ImportBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'identifier_column' => ['required', 'string'],
            'name_column' => ['nullable', 'string'],
            'participant_type_column' => ['nullable', 'string'],
            'attended_at_column' => ['nullable', 'string'],
        ]);

        $batch->update(['column_mapping' => $data, 'status' => ImportBatch::STATUS_MAPPED]);
        $this->importService->runPreview($batch);

        return redirect()->route('sport.admin.import.preview', $batch);
    }

    public function preview(ImportBatch $batch): View
    {
        $rows = $batch->rows()->orderBy('row_number')->get();

        return view('sport.admin.import.preview', ['batch' => $batch, 'rows' => $rows]);
    }

    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        if ($batch->status === ImportBatch::STATUS_COMMITTED) {
            return back()->with('error', 'Batch ini sudah dikonfirmasi sebelumnya.');
        }

        $this->importService->commit($batch, $request->user()->id);

        return redirect()->route('sport.admin.import.preview', $batch)
            ->with('success', "Import dikonfirmasi: {$batch->valid_rows} baris valid diproses, {$batch->duplicate_rows} duplikat dilewati, {$batch->invalid_rows} invalid, {$batch->unmatched_rows} tidak cocok.");
    }
}
