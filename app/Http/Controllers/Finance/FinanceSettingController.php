<?php

namespace App\Http\Controllers\Finance;

use App\Models\FinanceSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FinanceSettingController extends Controller
{
    public function index()
    {
        $settings = FinanceSetting::orderBy('key')->get();
        return view('finance.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings.active_employee_min_hr_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [
            'settings.active_employee_min_hr_percent.integer' => 'Ambang batas harus berupa angka bulat.',
            'settings.active_employee_min_hr_percent.min'     => 'Ambang batas minimal 0%.',
            'settings.active_employee_min_hr_percent.max'     => 'Ambang batas maksimal 100%.',
        ]);

        foreach ($request->input('settings', []) as $key => $value) {
            FinanceSetting::setValue($key, $value ?? '0');
        }

        return back()->with('success', 'Setting Finance berhasil disimpan.');
    }
}
