<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecruitmentRetentionSettingController extends Controller
{
    public function edit()
    {
        $settings = [
            'applicant_incomplete_auto_reject_days' => RecruitmentSetting::getInt('applicant_incomplete_auto_reject_days', 14),
            'retention_failed_test_days' => RecruitmentSetting::getInt('retention_failed_test_days', 14),
            'retention_rejected_days' => RecruitmentSetting::getInt('retention_rejected_days', 14),
            'retention_blacklist_days' => RecruitmentSetting::getInt('retention_blacklist_days', 7),
        ];

        return view('hrd.settings.recruitment_retention', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_incomplete_auto_reject_days' => ['required', 'integer', 'min:1', 'max:365'],
            'retention_failed_test_days' => ['required', 'integer', 'min:1', 'max:365'],
            'retention_rejected_days' => ['required', 'integer', 'min:1', 'max:365'],
            'retention_blacklist_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        RecruitmentSetting::setValue('applicant_incomplete_auto_reject_days', (string) $data['applicant_incomplete_auto_reject_days']);
        RecruitmentSetting::setValue('retention_failed_test_days', (string) $data['retention_failed_test_days']);
        RecruitmentSetting::setValue('retention_rejected_days', (string) $data['retention_rejected_days']);
        RecruitmentSetting::setValue('retention_blacklist_days', (string) $data['retention_blacklist_days']);

        return back()->with('success', 'Pengaturan retensi recruitment berhasil disimpan.');
    }
}
