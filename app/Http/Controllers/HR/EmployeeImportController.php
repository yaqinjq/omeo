<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeImportSession;
use App\Services\HR\EmployeeImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeeImportController extends Controller
{
    public function __construct(private readonly EmployeeImportService $service) {}

    public function index()
    {
        $sessions = EmployeeImportSession::with('importer')
            ->orderByDesc('id')
            ->paginate(20);

        return view('hr.employee_import.index', compact('sessions'));
    }

    public function create()
    {
        return view('hr.employee_import.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ], [
            'file.required' => 'File XLS wajib dipilih.',
            'file.mimes'    => 'Format file harus XLS atau XLSX.',
            'file.max'      => 'Ukuran file maksimal 20MB.',
        ]);

        $uploaded   = $request->file('file');
        $origName   = $uploaded->getClientOriginalName();
        $storedPath = $uploaded->store('employee_imports', 'local');
        $absPath    = Storage::disk('local')->path($storedPath);

        try {
            $parsedRows = $this->service->parseFile($absPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);
            Log::error('EmployeeImport parseFile failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()]);
        }

        if (empty($parsedRows)) {
            Storage::disk('local')->delete($storedPath);
            return back()->withErrors(['file' => 'File tidak mengandung data karyawan (sheet "Page 0" kosong atau format tidak dikenali).']);
        }

        $session = EmployeeImportSession::create([
            'source_file_name' => $origName,
            'source_file_path' => $storedPath,
            'total_rows'       => count($parsedRows),
            'status'           => 'draft',
            'imported_by'      => Auth::id(),
            'parsed_data'      => json_encode($parsedRows),
        ]);

        return redirect()->route('hr.employee-import.preview', $session);
    }

    public function preview(EmployeeImportSession $session)
    {
        if ($session->status !== 'draft') {
            return redirect()->route('hr.employee-import.index')
                ->with('error', 'Sesi import ini sudah diproses.');
        }

        $analysis = $this->service->preview($session->parsed_rows);

        return view('hr.employee_import.preview', compact('session', 'analysis'));
    }

    public function confirm(Request $request, EmployeeImportSession $session)
    {
        if ($session->status === 'completed') {
            return redirect()->route('hr.employee-import.status', $session)
                ->with('info', 'Sesi import ini sudah selesai diproses.');
        }

        if ($session->status === 'processing') {
            return redirect()->route('hr.employee-import.status', $session)
                ->with('info', 'Import sedang diproses...');
        }

        if ($session->status !== 'draft') {
            return redirect()->route('hr.employee-import.index')
                ->with('error', 'Sesi import tidak valid.');
        }

        if (empty($session->parsed_rows)) {
            return back()->withErrors(['error' => 'Data preview tidak ditemukan. Upload ulang file.']);
        }

        $session->update(['status' => 'processing']);

        \App\Jobs\ProcessEmployeeImport::dispatch($session->id, Auth::id());

        return redirect()->route('hr.employee-import.status', $session)
            ->with('success', 'Import sedang diproses di background. Halaman ini otomatis refresh.');
    }

    public function status(EmployeeImportSession $session)
    {
        return view('hr.employee_import.status', compact('session'));
    }
}
