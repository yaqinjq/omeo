<?php

namespace App\Http\Controllers;

use App\Models\AppraisalPeriod;
use Illuminate\Http\Request;

class AppraisalPeriodController extends Controller
{
    public function index()
    {
        $periods = AppraisalPeriod::orderByDesc('id')->paginate(15);
        return view('appraisal_periods.index', compact('periods'));
    }

    public function create()
    {
        return view('appraisal_periods.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'type' => 'required|in:probation,annual,contract_renewal',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        AppraisalPeriod::create($data);
        return redirect()->route('appraisal-periods.index')->with('success','Periode dibuat.');
    }

    public function edit(AppraisalPeriod $appraisal_period)
    {
        return view('appraisal_periods.edit', ['period'=>$appraisal_period]);
    }

    public function update(Request $request, AppraisalPeriod $appraisal_period)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'type' => 'required|in:probation,annual,contract_renewal',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $appraisal_period->update($data);
        return redirect()->route('appraisal-periods.index')->with('success','Periode diupdate.');
    }

    public function destroy(AppraisalPeriod $appraisal_period)
    {
        $appraisal_period->delete();
        return redirect()->route('appraisal-periods.index')->with('success','Periode dihapus.');
    }

    public function show(AppraisalPeriod $appraisal_period)
    {
        return view('appraisal_periods.show', ['period'=>$appraisal_period]);
    }
}
