<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Appraisal;
use Carbon\Carbon;

class DashboardService
{
    public function summary(): array
    {
        $today = Carbon::today();

        // Karyawan probation yang mendekati appraisal (default: 14 hari menuju probation_end_date)
        $dueSoon = Employee::probation()
            ->whereNotNull('probation_end_date')
            ->whereBetween('probation_end_date', [$today, $today->copy()->addDays(14)])
            ->count();

        // Catatan: pipeline pelamar belum ada di schema dump -> placeholder angka 0
        $totalApplicants = 0;
        $acceptedNoOffering = 0;
        $offeringNoAppraisal = 0;

        // Appraisal yang sudah diisi hari ini
        $appraisedToday = Appraisal::whereDate('date_appraised', $today)->count();

        return [
            'today' => $today->toDateString(),
            'due_appraisal_soon' => $dueSoon,
            'total_applicants' => $totalApplicants,
            'accepted_no_offering' => $acceptedNoOffering,
            'offering_no_appraisal' => $offeringNoAppraisal,
            'appraised_today' => $appraisedToday,
            'suggested_activities' => $this->suggestedActivities($dueSoon),
        ];
    }

    protected function suggestedActivities(int $dueSoon): array
    {
        $items = [];

        if ($dueSoon > 0) {
            $items[] = "Review karyawan probation yang mendekati akhir masa probation & siapkan jadwal appraisal.";
        }
        $items[] = "Cek dokumen karyawan baru yang belum lengkap (KTP/KK/NPWP/BPJS) — modul kandidat perlu diaktifkan.";
        $items[] = "Cek materi LMS yang belum 100% selesai (agar tidak mengganggu kelulusan appraisal).";

        return $items;
    }
}
