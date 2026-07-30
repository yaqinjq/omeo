<?php

namespace App\Http\Controllers;

use App\Models\AppraisalCriteriaTemplate;
use App\Models\AppraisalIndicator;
use Illuminate\Http\Request;

class AppraisalIndicatorController extends Controller
{
    public function index(Request $request)
    {
        $indicators = AppraisalIndicator::with('template')
            ->when($request->filled('template_id'), fn ($q) => $q->where('template_id', $request->integer('template_id')))
            ->orderBy('category')->orderBy('id')->paginate(20)->withQueryString();
        $templates = AppraisalCriteriaTemplate::orderByDesc('is_default')->orderBy('name')->get();
        return view('appraisal_indicators.index', compact('indicators', 'templates'));
    }

    public function create(Request $request)
    {
        $templates = AppraisalCriteriaTemplate::orderByDesc('is_default')->orderBy('name')->get();
        $selectedTemplateId = $request->integer('template_id') ?: $templates->firstWhere('is_default', true)?->id;
        return view('appraisal_indicators.create', compact('templates', 'selectedTemplateId'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        AppraisalIndicator::create($data);
        return redirect()->route('appraisal-criteria-templates.edit', $data['template_id'])->with('success', 'Indikator dibuat.');
    }

    public function edit(AppraisalIndicator $appraisal_indicator)
    {
        $templates = AppraisalCriteriaTemplate::orderByDesc('is_default')->orderBy('name')->get();
        return view('appraisal_indicators.edit', ['indicator' => $appraisal_indicator, 'templates' => $templates]);
    }

    public function update(Request $request, AppraisalIndicator $appraisal_indicator)
    {
        $appraisal_indicator->update($this->validated($request));
        return redirect()->route('appraisal-criteria-templates.edit', $appraisal_indicator->template_id)->with('success', 'Indikator diupdate.');
    }

    public function destroy(AppraisalIndicator $appraisal_indicator)
    {
        $templateId = $appraisal_indicator->template_id;
        $appraisal_indicator->delete();
        return $templateId
            ? redirect()->route('appraisal-criteria-templates.edit', $templateId)->with('success', 'Indikator dihapus.')
            : redirect()->route('appraisal-indicators.index')->with('success', 'Indikator dihapus.');
    }

    public function show(AppraisalIndicator $appraisal_indicator)
    {
        return view('appraisal_indicators.show', ['indicator' => $appraisal_indicator]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category'    => 'required|string|max:100',
            'question'    => 'required|string',
            'description' => 'nullable|string',
            'weight'      => 'required|integer|min:1|max:100',
            'template_id' => 'required|exists:appraisal_criteria_templates,id',
            'rubric'      => 'nullable|array',
            'rubric.*'    => 'nullable|string|max:1000',
        ]);
        $data['scale_labels'] = $this->rubricToScaleLabels($data['rubric'] ?? []);
        unset($data['rubric']);

        return $data;
    }

    /**
     * Rubric inputs come in as skor => text (skor 5..1). Drop empty entries so
     * partially-filled rubrics don't store misleading blank descriptions.
     */
    private function rubricToScaleLabels(array $rubric): ?array
    {
        $labels = array_filter($rubric, fn ($text) => filled($text));
        return $labels === [] ? null : $labels;
    }
}
