<?php

namespace App\Http\Controllers;

use App\Models\AppraisalCriteriaTemplate;
use App\Models\AppraisalIndicator;
use Illuminate\Http\Request;

class AppraisalIndicatorController extends Controller
{
    /**
     * lokasi_kerja per tab. "operational" menggabungkan outlet+production
     * jadi satu tab (permintaan HRD supaya tidak ada tab per-departemen dulu
     * sampai kebutuhan itu benar-benar datang); "umum" menampung template
     * tanpa lokasi_kerja (mis. template Default) supaya tidak ada kriteria
     * yang hilang dari tampilan mana pun.
     */
    private const TABS = [
        'office'      => ['label' => 'Office',      'lokasi_kerja' => ['office']],
        'operational' => ['label' => 'Operational',  'lokasi_kerja' => ['outlet', 'production']],
        'umum'        => ['label' => 'Umum / Default', 'lokasi_kerja' => null],
    ];

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'office');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'office';
        }
        $lokasiKerjaList = self::TABS[$tab]['lokasi_kerja'];

        $indicators = AppraisalIndicator::with('template')
            ->whereHas('template', function ($q) use ($lokasiKerjaList) {
                $lokasiKerjaList === null
                    ? $q->whereNull('lokasi_kerja')
                    : $q->whereIn('lokasi_kerja', $lokasiKerjaList);
            })
            ->orderBy('category')->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString();

        $tabCounts = collect(self::TABS)->mapWithKeys(function ($cfg, $key) {
            $count = AppraisalIndicator::whereHas('template', function ($q) use ($cfg) {
                $cfg['lokasi_kerja'] === null
                    ? $q->whereNull('lokasi_kerja')
                    : $q->whereIn('lokasi_kerja', $cfg['lokasi_kerja']);
            })->count();
            return [$key => $count];
        });

        $templates = AppraisalCriteriaTemplate::orderByDesc('is_default')->orderBy('name')->get();

        return view('appraisal_indicators.index', [
            'indicators' => $indicators,
            'templates'  => $templates,
            'tabs'       => self::TABS,
            'activeTab'  => $tab,
            'tabCounts'  => $tabCounts,
        ]);
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
        // Tidak ada FK/cascade di DB untuk appraisal_details.appraisal_indicator_id
        // — cek manual di sini supaya hapus kriteria tidak mengubah histori
        // penilaian yang sudah pernah diisi evaluator jadi baris "yatim".
        $usageCount = \Illuminate\Support\Facades\DB::table('appraisal_details')
            ->where('appraisal_indicator_id', $appraisal_indicator->id)
            ->count();

        if ($usageCount > 0) {
            return back()->with('error', "Kriteria ini sudah dipakai di {$usageCount} penilaian appraisal — tidak bisa dihapus langsung. Gunakan fitur gabung kriteria kalau ingin melebur ke kriteria lain.");
        }

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

    /**
     * Geser urutan kriteria naik/turun — hanya menukar posisi dengan sibling
     * TERDEKAT di kategori & template yang sama (urutan tampil dikelompokkan
     * per kategori dulu, jadi tidak masuk akal menukar lintas kategori).
     * Menomori ulang seluruh sibling di kategori itu tiap kali digeser
     * (bukan cuma menukar 2 angka) supaya tetap konsisten walau sort_order
     * lama masih sama semua (default 0) atau ada celah angka.
     */
    public function moveOrder(Request $request, AppraisalIndicator $appraisal_indicator)
    {
        $direction = $request->input('direction');
        abort_unless(in_array($direction, ['up', 'down'], true), 400);

        $siblings = AppraisalIndicator::where('template_id', $appraisal_indicator->template_id)
            ->where('category', $appraisal_indicator->category)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index    = $siblings->search(fn ($i) => $i->id === $appraisal_indicator->id);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $swapWith < 0 || $swapWith >= $siblings->count()) {
            return back();
        }

        $reordered = $siblings->all();
        [$reordered[$index], $reordered[$swapWith]] = [$reordered[$swapWith], $reordered[$index]];

        \Illuminate\Support\Facades\DB::transaction(function () use ($reordered) {
            foreach ($reordered as $i => $indicator) {
                $indicator->update(['sort_order' => $i]);
            }
        });

        return redirect()->route('appraisal-criteria-templates.edit', $appraisal_indicator->template_id)
            ->with('success', 'Urutan kriteria diperbarui.');
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
