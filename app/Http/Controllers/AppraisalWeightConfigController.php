<?php

namespace App\Http\Controllers;

use App\Models\AppraisalWeightConfig;
use Illuminate\Http\Request;

class AppraisalWeightConfigController extends Controller
{
    private const TYPES = ['probation', 'annual', 'contract_renewal'];

    private const LABELS = [
        'probation'         => 'Probation',
        'annual'            => 'Annual Review',
        'contract_renewal'  => 'Contract Renewal',
    ];

    public function index()
    {
        abort_if(! in_array(auth()->user()->role, ['admin', 'hrd'], true), 403);

        // Ensure global config exists
        $global = AppraisalWeightConfig::firstOrCreate(
            ['scope' => 'global', 'appraisal_type' => null],
            [
                'weight_criteria' => 50, 'weight_kpi' => 30, 'weight_training' => 20,
                'weight_skill' => 0, 'weight_position' => 0,
            ]
        );

        // Load per-type configs
        $perType = [];
        foreach (self::TYPES as $t) {
            $perType[$t] = AppraisalWeightConfig::where('scope', 'per_type')
                ->where('appraisal_type', $t)
                ->first();
        }

        return view('appraisals.weight_config.index', [
            'global'     => $global,
            'perType'    => $perType,
            'typeLabels' => self::LABELS,
        ]);
    }

    public function save(Request $request)
    {
        abort_if(! in_array(auth()->user()->role, ['admin', 'hrd'], true), 403);

        $scope = $request->input('scope');
        $type  = $request->input('appraisal_type');

        if (! in_array($scope, ['global', 'per_type'], true)) {
            return back()->withErrors(['scope' => 'Scope tidak valid.']);
        }
        if ($scope === 'per_type' && ! in_array($type, self::TYPES, true)) {
            return back()->withErrors(['appraisal_type' => 'Tipe appraisal tidak valid.']);
        }

        $weights = [
            'weight_criteria' => (float) $request->input('weight_criteria', 0),
            'weight_kpi'      => (float) $request->input('weight_kpi', 0),
            'weight_training' => (float) $request->input('weight_training', 0),
            'weight_skill'    => (float) $request->input('weight_skill', 0),
            'weight_position' => (float) $request->input('weight_position', 0),
        ];

        $total = array_sum($weights);
        if (abs($total - 100) > 0.01) {
            return back()
                ->withInput()
                ->withErrors(['total' => "Total bobot harus 100%. Saat ini: {$total}%"]);
        }

        foreach ($weights as $key => $val) {
            if ($val < 0 || $val > 100) {
                return back()->withInput()->withErrors([$key => 'Setiap bobot harus antara 0-100.']);
            }
        }

        AppraisalWeightConfig::updateOrCreate(
            [
                'scope'          => $scope,
                'appraisal_type' => $scope === 'global' ? null : $type,
            ],
            array_merge($weights, ['updated_by' => auth()->id()])
        );

        $label = $scope === 'global' ? 'Global' : (self::LABELS[$type] ?? $type);
        return back()->with('success', "Setting bobot [{$label}] berhasil disimpan.");
    }

    public function destroy(int $id)
    {
        abort_if(! in_array(auth()->user()->role, ['admin', 'hrd'], true), 403);

        $cfg = AppraisalWeightConfig::findOrFail($id);

        if ($cfg->scope === 'global') {
            return back()->withErrors(['delete' => 'Setting global tidak dapat dihapus.']);
        }

        $cfg->delete();
        $label = self::LABELS[$cfg->appraisal_type] ?? $cfg->appraisal_type;
        return back()->with('success', "Setting per-tipe [{$label}] dihapus. Sekarang menggunakan setting global.");
    }
}
