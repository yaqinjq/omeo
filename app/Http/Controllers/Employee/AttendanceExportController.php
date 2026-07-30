<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceExportController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    public function index(Request $request)
    {
        $user     = Auth::user();
        $employee = $this->resolveEmployee($user);

        $attendanceData = [];
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if ($dateFrom && $dateTo) {
            $from = Carbon::parse($dateFrom);
            $to   = Carbon::parse($dateTo);
            if ($to->diffInDays($from) > 31) {
                $to = $from->copy()->addDays(30);
                $dateTo = $to->format('Y-m-d');
            }
            $attendanceData = $this->getAttendanceData($user, $from, $to);
        }

        return view('employee.attendance_export.index', compact(
            'employee', 'dateFrom', 'dateTo', 'attendanceData'
        ));
    }

    public function export(Request $request)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $request->validate([
            'date_from' => 'required|date|before_or_equal:today',
            'date_to'   => 'required|date|after_or_equal:date_from|before_or_equal:today',
        ], [
            'date_from.required'      => 'Tanggal mulai wajib diisi.',
            'date_to.required'        => 'Tanggal akhir wajib diisi.',
            'date_to.after_or_equal'  => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'date_to.before_or_equal' => 'Tanggal akhir tidak boleh melebihi hari ini.',
        ]);

        $user     = Auth::user();
        $employee = $this->resolveEmployee($user);
        $from     = Carbon::parse($request->date_from)->startOfDay();
        $to       = Carbon::parse($request->date_to)->endOfDay();

        if ($to->diffInDays($from) > 31) {
            return back()->withErrors(['date_to' => 'Rentang tanggal maksimal 31 hari.'])->withInput();
        }

        $rows = $this->getAttendanceData($user, $from, $to);
        $nama = $employee ? $employee->full_name : $user->name;

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Kehadiran');

        // Baris info karyawan
        $sheet->setCellValue('A1', 'REKAP KEHADIRAN KARYAWAN');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Nama Karyawan');
        $sheet->setCellValue('B2', ': ' . $nama);
        $sheet->setCellValue('A3', 'Periode');
        $sheet->setCellValue('B3', ': ' . $from->format('d/m/Y') . ' s/d ' . $to->format('d/m/Y'));

        if ($employee) {
            $noKaryawan = $employee->nokom ?? $employee->employee_number ?? '-';
            $sheet->setCellValue('A4', 'No. Karyawan');
            $sheet->setCellValue('B4', ': ' . $noKaryawan);
            $sheet->setCellValue('A5', 'Jabatan');
            $sheet->setCellValue('B5', ': ' . ($employee->jabatan ?? '-'));
        }

        // Header tabel
        $headerRow = 7;
        $headers   = ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Jam Keluar', 'Durasi', 'Status'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . $headerRow, $h);
            $sheet->getStyle($col . $headerRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($col . $headerRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
            $sheet->getStyle($col . $headerRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Data
        if (empty($rows)) {
            $r = $headerRow + 1;
            $sheet->setCellValue('A' . $r, 'Tidak ada data kehadiran pada periode ini.');
            $sheet->mergeCells('A' . $r . ':G' . $r);
        } else {
            foreach ($rows as $i => $row) {
                $r = $headerRow + 1 + $i;
                $sheet->setCellValue('A' . $r, $i + 1);
                $sheet->setCellValue('B' . $r, $row['tanggal']);
                $sheet->setCellValue('C' . $r, $row['hari']);
                $sheet->setCellValue('D' . $r, $row['jam_masuk'] ?? '-');
                $sheet->setCellValue('E' . $r, $row['jam_keluar'] ?? '-');
                $sheet->setCellValue('F' . $r, $row['durasi']);
                $sheet->setCellValue('G' . $r, $row['status']);
            }
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Kehadiran_'
            . str_replace(' ', '_', $nama) . '_'
            . $from->format('dMY') . '-' . $to->format('dMY') . '.xlsx';

        $writer   = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'kehadiran_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function namaHari(string $dayEn): string
    {
        return match ($dayEn) {
            'Sunday'    => 'Minggu',
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            default     => $dayEn,
        };
    }

    private function resolveEmployee(User $user): ?Employee
    {
        if ($user->employee_id ?? null) {
            return Employee::find($user->employee_id);
        }
        if ($user->email ?? null) {
            return Employee::where('email_private', $user->email)->first();
        }
        return null;
    }

    private function getAttendanceData(User $user, Carbon $from, Carbon $to): array
    {
        $sessions = DB::table('attendance_sessions')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('work_date')
            ->get(['work_date', 'first_in_at_utc', 'last_out_at_utc', 'total_work_minutes', 'status']);

        $rows = [];
        foreach ($sessions as $s) {
            $tgl = Carbon::parse($s->work_date);

            $jamMasuk  = $s->first_in_at_utc
                ? Carbon::parse($s->first_in_at_utc)->timezone(self::TIMEZONE)->format('H:i')
                : null;
            $jamKeluar = $s->last_out_at_utc
                ? Carbon::parse($s->last_out_at_utc)->timezone(self::TIMEZONE)->format('H:i')
                : null;

            $menit  = (int) ($s->total_work_minutes ?? 0);
            $durasi = $menit > 0
                ? floor($menit / 60) . 'j ' . ($menit % 60) . 'm'
                : '-';

            $statusLabel = match ($s->status) {
                'present'    => 'Hadir',
                'late'       => 'Terlambat',
                'incomplete' => 'Belum Clock-Out',
                'absent'     => 'Tidak Hadir',
                default      => ucfirst($s->status ?? '-'),
            };

            $rows[] = [
                'tanggal'    => $tgl->format('d/m/Y'),
                'hari'       => $this->namaHari($tgl->format('l')),
                'jam_masuk'  => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'durasi'     => $durasi,
                'status'     => $statusLabel,
                'raw_status' => $s->status,
            ];
        }

        return $rows;
    }
}
