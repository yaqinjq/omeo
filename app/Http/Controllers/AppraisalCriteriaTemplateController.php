<?php

namespace App\Http\Controllers;

use App\Models\AppraisalCriteriaTemplate;
use App\Models\AppraisalIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppraisalCriteriaTemplateController extends Controller
{
    public function index()
    {
        $templates = AppraisalCriteriaTemplate::withCount('indicators')->orderByDesc('is_default')->orderBy('name')->get();
        return view('appraisal_criteria_templates.index', compact('templates'));
    }

    public function create()
    {
        return view('appraisal_criteria_templates.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = AppraisalCriteriaTemplate::create($data);

        return redirect()->route('appraisal-criteria-templates.edit', $template)
            ->with('success', 'Template dibuat. Silakan tambahkan kriteria penilaian ke template ini.');
    }

    public function edit(AppraisalCriteriaTemplate $appraisal_criteria_template)
    {
        $appraisal_criteria_template->load('indicators');
        return view('appraisal_criteria_templates.edit', ['template' => $appraisal_criteria_template]);
    }

    public function update(Request $request, AppraisalCriteriaTemplate $appraisal_criteria_template)
    {
        $appraisal_criteria_template->update($this->validated($request));
        return redirect()->route('appraisal-criteria-templates.index')->with('success', 'Template diupdate.');
    }

    public function destroy(AppraisalCriteriaTemplate $appraisal_criteria_template)
    {
        if ($appraisal_criteria_template->is_default) {
            return back()->with('error', 'Template default tidak dapat dihapus.');
        }

        if ($appraisal_criteria_template->indicators()->exists()) {
            return back()->with('error', 'Template masih memiliki kriteria. Pindahkan atau hapus kriterianya dulu.');
        }

        $appraisal_criteria_template->delete();
        return redirect()->route('appraisal-criteria-templates.index')->with('success', 'Template dihapus.');
    }

    /**
     * Clone all indicators from an existing template into a brand new one, so
     * HRD can start from "Default" (or any other template) instead of typing
     * every kriteria from scratch.
     */
    public function duplicate(Request $request, AppraisalCriteriaTemplate $appraisal_criteria_template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $newTemplate = DB::transaction(function () use ($appraisal_criteria_template, $data) {
            $newTemplate = AppraisalCriteriaTemplate::create([
                'name'         => $data['name'],
                'description'  => $appraisal_criteria_template->description,
                'lokasi_kerja' => null,
                'is_default'   => false,
                'is_active'    => true,
            ]);

            foreach ($appraisal_criteria_template->indicators as $indicator) {
                AppraisalIndicator::create([
                    'template_id'  => $newTemplate->id,
                    'category'     => $indicator->category,
                    'question'     => $indicator->question,
                    'description'  => $indicator->description,
                    'weight'       => $indicator->weight,
                    'max_score'    => $indicator->max_score,
                    'scale_labels' => $indicator->scale_labels,
                ]);
            }

            return $newTemplate;
        });

        return redirect()->route('appraisal-criteria-templates.edit', $newTemplate)
            ->with('success', 'Template berhasil diduplikasi. Silakan sesuaikan kriterianya.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string',
            'lokasi_kerja' => 'nullable|in:office,outlet,production',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
