<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContractTemplateController extends Controller
{
    /** @var array<int,string> */
    private array $requiredPlaceholders = [
        '{{candidate_name}}',
        '{{candidate_nik}}',
        '{{candidate_email}}',
        '{{candidate_phone}}',
        '{{candidate_address}}',
        '{{position_name}}',
        '{{outlet_name}}',
        '{{contract_number}}',
        '{{today_date}}',
        '{{hrd_name}}',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $templates = ContractTemplate::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->where('type', ContractTemplate::TYPE_DAILY_WORKER)
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('hrd.contract_templates.index', [
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return view('hrd.contract_templates.create', [
            'template' => new ContractTemplate([
                'type' => ContractTemplate::TYPE_DAILY_WORKER,
                'numbering_format' => '{prefix}{YYYY}{MM}{SEQ4}',
                'next_sequence' => 1,
                'is_builder_mode' => true,
                'document_title' => 'SURAT PERJANJIAN KERJA DAILY WORKER',
                'opening_paragraph' => 'Pada hari ini, {{today_date}}, telah dibuat perjanjian kerja antara perusahaan dan pekerja berikut.',
                'main_content' => "1. Pihak Pertama: Perusahaan\n2. Pihak Kedua: {{candidate_name}}\n3. Posisi: {{position_name}}\n4. Outlet: {{outlet_name}}\n5. Alamat: {{candidate_address}}\n\nHak dan kewajiban serta ketentuan kerja mengikuti kebijakan perusahaan yang berlaku.",
                'closing_paragraph' => 'Demikian surat perjanjian ini dibuat untuk dipatuhi bersama.',
                'signatories_json' => $this->defaultSignatories(),
            ]),
            'requiredPlaceholders' => $this->requiredPlaceholders,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        if ($request->hasFile('logo_file')) {
            $data['logo_path'] = $request->file('logo_file')->store('contract_templates/logos', 'public');
        }

        $data['type'] = ContractTemplate::TYPE_DAILY_WORKER;
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['is_builder_mode'] = true;
        $data['signatories_json'] = $this->normalizeSignatories($data['signatories'] ?? []);
        $data['body_html'] = $this->buildBodyHtmlFromSections($data);
        $data['placeholders_json'] = $this->extractPlaceholders(implode("\n", [
            $data['body_html'],
            $data['letterhead_html'] ?? '',
            $data['opening_paragraph'] ?? '',
            $data['main_content'] ?? '',
            $data['closing_paragraph'] ?? '',
            collect($data['signatories_json'])->pluck('name')->implode("\n"),
        ]));

        unset($data['signatories']);

        DB::transaction(function () use ($data, $request): void {
            $template = ContractTemplate::create($data);

            if ($template->is_active) {
                $this->activateSingle($template, $request->user()?->id);
            }
        });

        return redirect()->route('hrd.contract-templates.index')
            ->with('success', 'Template kontrak berhasil dibuat.');
    }

    public function edit(ContractTemplate $contractTemplate)
    {
        abort_if($contractTemplate->type !== ContractTemplate::TYPE_DAILY_WORKER, 404);

        if (empty($contractTemplate->main_content) && !empty($contractTemplate->body_html)) {
            $contractTemplate->main_content = strip_tags((string) $contractTemplate->body_html);
        }

        if (empty($contractTemplate->signatories_json)) {
            $contractTemplate->signatories_json = $this->defaultSignatories();
        }

        return view('hrd.contract_templates.edit', [
            'template' => $contractTemplate,
            'requiredPlaceholders' => $this->requiredPlaceholders,
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate)
    {
        abort_if($contractTemplate->type !== ContractTemplate::TYPE_DAILY_WORKER, 404);

        $data = $this->validatedData($request, $contractTemplate->id);

        if ($request->hasFile('logo_file')) {
            if (!empty($contractTemplate->logo_path)) {
                Storage::disk('public')->delete($contractTemplate->logo_path);
            }
            $data['logo_path'] = $request->file('logo_file')->store('contract_templates/logos', 'public');
        }

        $data['updated_by'] = $request->user()?->id;
        $data['is_builder_mode'] = true;
        $data['signatories_json'] = $this->normalizeSignatories($data['signatories'] ?? []);
        $data['body_html'] = $this->buildBodyHtmlFromSections($data);
        $data['placeholders_json'] = $this->extractPlaceholders(implode("\n", [
            $data['body_html'],
            $data['letterhead_html'] ?? '',
            $data['opening_paragraph'] ?? '',
            $data['main_content'] ?? '',
            $data['closing_paragraph'] ?? '',
            collect($data['signatories_json'])->pluck('name')->implode("\n"),
        ]));

        unset($data['signatories']);

        DB::transaction(function () use ($contractTemplate, $data, $request): void {
            $contractTemplate->update($data);

            if ($contractTemplate->is_active) {
                $this->activateSingle($contractTemplate, $request->user()?->id);
            }
        });

        return redirect()->route('hrd.contract-templates.index')
            ->with('success', 'Template kontrak berhasil diperbarui.');
    }

    public function destroy(ContractTemplate $contractTemplate)
    {
        abort_if($contractTemplate->type !== ContractTemplate::TYPE_DAILY_WORKER, 404);

        if (!empty($contractTemplate->logo_path)) {
            Storage::disk('public')->delete($contractTemplate->logo_path);
        }

        $contractTemplate->delete();

        return redirect()->route('hrd.contract-templates.index')
            ->with('success', 'Template kontrak berhasil dihapus.');
    }

    public function activate(Request $request, ContractTemplate $contractTemplate)
    {
        abort_if($contractTemplate->type !== ContractTemplate::TYPE_DAILY_WORKER, 404);

        $this->activateSingle($contractTemplate, $request->user()?->id);

        return back()->with('success', 'Template berhasil diaktifkan. Template lain otomatis nonaktif.');
    }

    public function deactivate(Request $request, ContractTemplate $contractTemplate)
    {
        abort_if($contractTemplate->type !== ContractTemplate::TYPE_DAILY_WORKER, 404);

        $contractTemplate->update([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Template berhasil dinonaktifkan.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validatedData(Request $request, ?int $templateId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'numbering_prefix' => ['nullable', 'string', 'max:100'],
            'numbering_format' => ['nullable', 'string', 'max:150', 'regex:/\{SEQ(?:4)?\}/'],
            'next_sequence' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'letterhead_html' => ['nullable', 'string'],
            'document_title' => ['required', 'string', 'max:200'],
            'opening_paragraph' => ['nullable', 'string'],
            'main_content' => ['required', 'string'],
            'closing_paragraph' => ['nullable', 'string'],
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'type' => ['nullable', Rule::in([ContractTemplate::TYPE_DAILY_WORKER])],
            'signatories' => ['nullable', 'array'],
            'signatories.*.role' => ['nullable', 'string', 'max:120'],
            'signatories.*.name' => ['nullable', 'string', 'max:150'],
        ], [
            'name.required' => 'Nama template wajib diisi.',
            'document_title.required' => 'Judul dokumen wajib diisi.',
            'main_content.required' => 'Isi utama kontrak wajib diisi.',
            'numbering_format.regex' => 'Format nomor kontrak harus mengandung {SEQ} atau {SEQ4}.',
            'logo_file.mimes' => 'Logo harus berformat JPG, PNG, atau WEBP.',
            'logo_file.max' => 'Ukuran logo maksimal 2MB.',
            'next_sequence.integer' => 'Nomor urut selanjutnya harus berupa angka.',
            'next_sequence.min' => 'Nomor urut selanjutnya minimal 1.',
        ]);

        unset($data['logo_file']);

        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['type'])) {
            $data['type'] = ContractTemplate::TYPE_DAILY_WORKER;
        }

        if (empty($data['next_sequence'])) {
            $data['next_sequence'] = 1;
        }

        return $data;
    }

    private function activateSingle(ContractTemplate $template, ?int $userId): void
    {
        DB::transaction(function () use ($template, $userId): void {
            ContractTemplate::query()
                ->where('type', $template->type)
                ->where('id', '!=', $template->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

            $template->update([
                'is_active' => true,
                'updated_by' => $userId,
            ]);
        });
    }

    /**
     * @param array<int,array<string,mixed>> $signatories
     * @return array<int,array{role:string,name:string}>
     */
    private function normalizeSignatories(array $signatories): array
    {
        $normalized = collect($signatories)
            ->map(function ($row) {
                return [
                    'role' => trim((string) data_get($row, 'role')),
                    'name' => trim((string) data_get($row, 'name')),
                ];
            })
            ->filter(fn ($row) => $row['role'] !== '' || $row['name'] !== '')
            ->values()
            ->all();

        if (empty($normalized)) {
            return $this->defaultSignatories();
        }

        return $normalized;
    }

    /**
     * @return array<int,array{role:string,name:string}>
     */
    private function defaultSignatories(): array
    {
        return [
            ['role' => 'Mengetahui, HRD', 'name' => '{{hrd_name}}'],
            ['role' => 'Daily Worker', 'name' => '{{candidate_name}}'],
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function buildBodyHtmlFromSections(array $data): string
    {
        $title = $this->textToHtml((string) ($data['document_title'] ?? 'SURAT PERJANJIAN KERJA'));
        $letterhead = $this->textToHtml((string) ($data['letterhead_html'] ?? ''));
        $opening = $this->textToHtml((string) ($data['opening_paragraph'] ?? ''));
        $main = $this->textToHtml((string) ($data['main_content'] ?? ''));
        $closing = $this->textToHtml((string) ($data['closing_paragraph'] ?? ''));
        $signatories = $this->normalizeSignatories($data['signatories'] ?? []);

        $signRows = collect($signatories)
            ->map(function (array $row): string {
                return '<td style="width:50%; text-align:center; vertical-align:top; padding-top:16px;">'
                    . '<div style="font-weight:600;">' . e($row['role']) . '</div>'
                    . '<div style="height:64px;"></div>'
                    . '<div style="font-weight:600;">' . e($row['name']) . '</div>'
                    . '</td>';
            })
            ->chunk(2)
            ->map(function ($chunk): string {
                $cells = $chunk->implode('');
                if ($chunk->count() === 1) {
                    $cells .= '<td style="width:50%;"></td>';
                }
                return '<tr>' . $cells . '</tr>';
            })
            ->implode('');

        return '<div style="font-family:DejaVu Sans, sans-serif; font-size:12px; line-height:1.5;">'
            . (!empty($letterhead) ? '<div style="margin-bottom:8px; text-align:center;">' . $letterhead . '</div><hr style="margin-bottom:12px;">' : '')
            . '<h2 style="text-align:center; font-size:16px; margin:0 0 12px;">' . $title . '</h2>'
            . (!empty($opening) ? '<p style="margin:0 0 10px;">' . $opening . '</p>' : '')
            . '<div style="margin:0 0 10px;">' . $main . '</div>'
            . (!empty($closing) ? '<p style="margin:0 0 12px;">' . $closing . '</p>' : '')
            . '<table style="width:100%; border-collapse:collapse; margin-top:20px;">' . $signRows . '</table>'
            . '</div>';
    }

    private function textToHtml(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        return nl2br(e($value));
    }

    /**
     * @return array<int,string>
     */
    private function extractPlaceholders(string $source): array
    {
        preg_match_all('/\{\{[a-zA-Z0-9_]+\}\}/', $source, $matches);

        $found = collect($matches[0] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        return collect($this->requiredPlaceholders)
            ->merge($found)
            ->unique()
            ->values()
            ->all();
    }
}
