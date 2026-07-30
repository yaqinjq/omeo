<?php

namespace App\Http\Controllers\Master;

use App\Exports\IqTemplateExport;
use App\Models\LegalEntity;
use App\Models\Outlet;
use App\Models\OutletPermit;
use App\Models\OutletPermitAttachment;
use App\Support\ImportHeaderValidator;
use App\Support\ImportTemplateColumn;
use App\Support\ImportTemplateSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class OutletController extends Controller
{
    private const PREVIEW_SESSION_KEY = 'outlet_import_preview';

    public function __construct(private readonly ImportHeaderValidator $headerValidator)
    {
    }

    public function index(Request $request)
    {
        $typeFilter = $request->get('type');

        $items = Outlet::query()
            ->with(['region:id,name', 'legalEntity:id,name,short_name'])
            ->withCount('permits')
            ->when($typeFilter, fn ($q) => $q->where('outlet_type', $typeFilter))
            ->orderBy('name')
            ->paginate($this->getPerPage(15))
            ->withQueryString();

        return view('master.outlets.index', [
            'items' => $items,
            'typeFilter' => $typeFilter,
            'importPreview' => session(self::PREVIEW_SESSION_KEY),
        ]);
    }

    public function create()
    {
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);

        return view('master.outlets.create', compact('legalEntities'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $outlet = Outlet::create($data);

        return redirect()->route('outlets.edit', $outlet)->with('success', 'Berhasil dibuat. Silakan lengkapi dokumen perizinan outlet bila sudah tersedia.');
    }

    public function edit(Outlet $outlet)
    {
        $outlet->load(['permits.attachments']);
        $legalEntities = LegalEntity::where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_name']);

        return view('master.outlets.edit', compact('outlet', 'legalEntities'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        $data = $this->validated($request, $outlet->id);
        $outlet->update($data);

        return redirect()->route('outlets.index')->with('success', 'Berhasil disimpan.');
    }

    public function destroy(Outlet $outlet)
    {
        $outlet->delete();

        return back()->with('success', 'Berhasil dihapus.');
    }

    public function export()
    {
        $rows = Outlet::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Outlet $outlet) => [
                $outlet->name,
                $outlet->brand_name,
                $outlet->external_id,
                $outlet->location,
                null,
                $outlet->latitude,
                $outlet->longitude,
                $outlet->radius_meters ?? $outlet->geofence_radius_m,
                $outlet->timezone,
                $outlet->work_start_time,
                $outlet->work_end_time,
            ])
            ->all();

        return Excel::download(
            new IqTemplateExport($this->schema()->exportHeaders(), $rows),
            'master-outlet-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function template()
    {
        return Excel::download(
            new IqTemplateExport($this->schema()->exportHeaders(), [[
                'Outlet Darmo',
                'MEO',
                'OUTLET-001',
                'Jl. Raya Darmo No. 1 Surabaya',
                'https://maps.google.com/?q=-7.286971,112.739687',
                '',
                '',
                50,
                'Asia/Jakarta',
                '08:00',
                '17:00',
            ]]),
            'template-import-outlet.xlsx'
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        try {
            $preview = $this->buildImportPreview($validated['file']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
        }

        $request->session()->put(self::PREVIEW_SESSION_KEY, $preview);

        return redirect()->route('outlets.index')
            ->with($preview['summary']['creates'] > 0 || $preview['summary']['updates'] > 0 ? 'success' : 'warning', 'Preview import master outlet siap. Periksa data yang akan dibuat atau diupdate lalu konfirmasi.');
    }

    public function confirmImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => ['required', 'string'],
        ]);

        $preview = $request->session()->get(self::PREVIEW_SESSION_KEY);
        if (! is_array($preview) || ($preview['token'] ?? null) !== $validated['preview_token']) {
            return redirect()->route('outlets.index')->with('error', 'Preview import sudah tidak tersedia atau token konfirmasi tidak valid. Upload file lagi untuk membuat preview baru.');
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'row_errors' => $preview['row_errors'] ?? [],
            'warnings' => $preview['warnings'] ?? [],
        ];

        foreach (($preview['actions'] ?? []) as $action) {
            $payload = (array) ($action['payload'] ?? []);
            $row = (int) ($action['row'] ?? 0);
            try {
                if (($action['type'] ?? null) === 'update') {
                    $existing = Outlet::query()->find((int) ($action['outlet_id'] ?? 0));
                    if (! $existing) {
                        $summary['failed']++;
                        $summary['row_errors'][] = ['row' => $row, 'message' => 'Outlet existing untuk update tidak ditemukan saat konfirmasi.'];
                        continue;
                    }

                    $existing->update($this->mergeImportedOutletPayload($existing, $payload, (string) ($action['raw_external_id'] ?? '')));
                    $summary['updated']++;
                    continue;
                }

                Outlet::query()->create($payload);
                $summary['created']++;
            } catch (\Throwable $exception) {
                report($exception);
                $summary['failed']++;
                $summary['row_errors'][] = ['row' => $row, 'message' => 'Gagal menyimpan data outlet saat konfirmasi import.'];
            }
        }

        $request->session()->forget(self::PREVIEW_SESSION_KEY);

        return redirect()->route('outlets.index')
            ->with($summary['created'] > 0 || $summary['updated'] > 0 ? 'success' : 'warning', 'Import master outlet selesai diproses.')
            ->with('import_summary', $summary);
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PREVIEW_SESSION_KEY);

        return redirect()->route('outlets.index')->with('warning', 'Preview import dibatalkan. Tidak ada data outlet yang diubah.');
    }

    public function storePermit(Request $request, Outlet $outlet): RedirectResponse
    {
        $data = $this->validatedPermit($request);
        $permit = $outlet->permits()->create(Arr::except($data, ['attachments']));
        $this->storePermitAttachments($permit, $request->file('attachments', []));

        return redirect()->route('outlets.edit', $outlet)->with('success', 'Dokumen perizinan outlet berhasil ditambahkan.');
    }

    public function updatePermit(Request $request, Outlet $outlet, OutletPermit $permit): RedirectResponse
    {
        abort_unless((int) $permit->outlet_id === (int) $outlet->id, 404);

        $data = $this->validatedPermit($request);
        $permit->update(Arr::except($data, ['attachments']));
        $this->storePermitAttachments($permit, $request->file('attachments', []));

        return redirect()->route('outlets.edit', $outlet)->with('success', 'Dokumen perizinan outlet berhasil diperbarui.');
    }

    public function destroyPermit(Outlet $outlet, OutletPermit $permit): RedirectResponse
    {
        abort_unless((int) $permit->outlet_id === (int) $outlet->id, 404);

        foreach ($permit->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $permit->delete();

        return redirect()->route('outlets.edit', $outlet)->with('success', 'Dokumen perizinan outlet berhasil dihapus.');
    }

    public function destroyPermitAttachment(Outlet $outlet, OutletPermit $permit, OutletPermitAttachment $attachment): RedirectResponse
    {
        abort_unless((int) $permit->outlet_id === (int) $outlet->id, 404);
        abort_unless((int) $attachment->outlet_permit_id === (int) $permit->id, 404);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return redirect()->route('outlets.edit', $outlet)->with('success', 'Lampiran izin outlet berhasil dihapus.');
    }

    private function buildImportPreview(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $match = $this->matchHeaders($rows);

        $warnings = $match->warnings();
        $rowErrors = [];
        $actions = [];
        $creates = [];
        $updates = [];
        $seenMatches = [];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeImportRow($match->mapRow($rawRow));
            if ($this->isEmptyRow(array_values($row))) {
                continue;
            }

            $request = new Request($row);
            try {
                $payload = $this->validated($request, null, false);
            } catch (ValidationException $exception) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => collect($exception->errors())->flatten()->first() ?? 'Data outlet tidak valid.',
                ];
                continue;
            }

            $rawExternalId = trim((string) ($row['external_id'] ?? ''));
            if ($rawExternalId !== '' && $this->externalIdHasDuplicateRows($rawExternalId)) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => 'External ID outlet ini sudah dipakai oleh lebih dari satu outlet existing. Rapikan data master terlebih dahulu sebelum import memakai External ID tersebut.',
                ];
                continue;
            }

            $normalizedName = Str::squish(trim((string) ($row['name'] ?? '')));
            if ($normalizedName === '') {
                $rowErrors[] = ['row' => $rowNumber, 'message' => 'Nama outlet wajib diisi.'];
                continue;
            }

            $existing = $this->findExistingOutlet($rawExternalId, $normalizedName);
            $matchSource = $rawExternalId !== '' ? 'external_id' : 'name';
            $matchKey = $rawExternalId !== ''
                ? 'external_id:' . Str::lower($rawExternalId)
                : 'name:' . Str::lower($normalizedName);

            if (isset($seenMatches[$matchKey])) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => 'Baris ini menduplikasi outlet yang sama di file import. Gunakan hanya satu baris untuk setiap outlet.',
                ];
                continue;
            }
            $seenMatches[$matchKey] = true;

            $previewItem = [
                'row' => $rowNumber,
                'name' => $payload['name'],
                'brand_name' => $payload['brand_name'],
                'external_id' => $payload['external_id'] ?? null,
                'timezone' => $payload['timezone'],
                'work_start_time' => $payload['work_start_time'] ?? null,
                'work_end_time' => $payload['work_end_time'] ?? null,
                'radius_meters' => $payload['radius_meters'] ?? null,
                'match_source' => $existing ? $matchSource : null,
                'existing_name' => $existing?->name,
            ];

            if ($existing) {
                $updates[] = $previewItem;
                $actions[] = [
                    'type' => 'update',
                    'row' => $rowNumber,
                    'outlet_id' => (int) $existing->id,
                    'raw_external_id' => $rawExternalId,
                    'payload' => $payload,
                ];
                continue;
            }

            $creates[] = $previewItem;
            $actions[] = [
                'type' => 'create',
                'row' => $rowNumber,
                'payload' => $payload,
            ];
        }

        return [
            'token' => (string) Str::uuid(),
            'summary' => [
                'creates' => count($creates),
                'updates' => count($updates),
                'failed' => count($rowErrors),
            ],
            'creates' => array_slice($creates, 0, 10),
            'updates' => array_slice($updates, 0, 10),
            'errors' => array_slice($rowErrors, 0, 10),
            'warnings' => $warnings,
            'row_errors' => $rowErrors,
            'actions' => $actions,
        ];
    }

    private function findExistingOutlet(string $rawExternalId, string $normalizedName): ?Outlet
    {
        if ($rawExternalId !== '') {
            return Outlet::query()->where('external_id', $rawExternalId)->first();
        }

        return Outlet::query()->whereRaw('LOWER(name) = ?', [Str::lower($normalizedName)])->first();
    }

    private function schema(): ImportTemplateSchema
    {
        return ImportTemplateSchema::make([
            ImportTemplateColumn::make('name', true, ['nama']),
            ImportTemplateColumn::make('brand_name', true, ['brand']),
            ImportTemplateColumn::make('external_id'),
            ImportTemplateColumn::make('location', false, ['alamat']),
            ImportTemplateColumn::make('maps_reference', false, ['link maps', 'maps', 'google_maps']),
            ImportTemplateColumn::make('latitude'),
            ImportTemplateColumn::make('longitude'),
            ImportTemplateColumn::make('radius_meters', false, ['radius']),
            ImportTemplateColumn::make('timezone', true),
            ImportTemplateColumn::make('work_start_time'),
            ImportTemplateColumn::make('work_end_time'),
        ]);
    }

    private function matchHeaders(array $rows): \App\Support\ImportHeaderMatchResult
    {
        $match = $this->headerValidator->match($rows[0] ?? [], $this->schema());
        $this->headerValidator->ensureValid($match, 'outlet');

        return $match;
    }

    private function validated(Request $request, ?int $id = null, bool $enforceUniqueExternalId = true): array
    {
        $input = $request->all();
        $input['external_id'] = $this->normalizeExternalId($input['external_id'] ?? null);
        $input['work_start_time'] = $this->normalizeTimeValue($input['work_start_time'] ?? null);
        $input['work_end_time'] = $this->normalizeTimeValue($input['work_end_time'] ?? null);
        $request->replace($input);

        $externalIdRules = ['nullable', 'string', 'max:100'];
        if ($this->shouldValidateUniqueExternalId($input['external_id'] ?? null, $id, $enforceUniqueExternalId)) {
            $externalIdRules[] = Rule::unique('outlets', 'external_id')->ignore($id)->whereNotNull('external_id');
        }

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'outlet_type' => ['required', Rule::in(['operational', 'payroll', 'production'])],
            'legal_entity_id' => ['nullable', 'integer', 'exists:legal_entities,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['required', 'string', 'max:50'],
            'external_id' => $externalIdRules,
            'maps_reference' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:1'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
        ];

        $data = $request->validate($rules, [
            'external_id.unique' => 'External ID outlet sudah dipakai. Gunakan External ID yang unik agar sinkronisasi lintas sistem aman.',
            'timezone.in' => 'Timezone outlet tidak valid.',
            'work_start_time.date_format' => 'Format jam masuk harus HH:MM.',
            'work_end_time.date_format' => 'Format jam pulang harus HH:MM.',
        ]);

        $data['name'] = Str::squish((string) $data['name']);
        $data['brand_name'] = Str::squish((string) $data['brand_name']);
        $data['outlet_type'] = $data['outlet_type'] ?? 'operational';
        $data['external_id'] = $this->normalizeExternalId($data['external_id'] ?? null);
        $data['location'] = trim((string) ($data['location'] ?? '')) ?: null;

        [$parsedLat, $parsedLng] = $this->extractCoordinatesFromReference(
            (string) ($data['maps_reference'] ?? ''),
            (string) ($data['location'] ?? '')
        );

        if (($data['latitude'] ?? null) === null && $parsedLat !== null) {
            $data['latitude'] = $parsedLat;
        }

        if (($data['longitude'] ?? null) === null && $parsedLng !== null) {
            $data['longitude'] = $parsedLng;
        }

        unset($data['maps_reference']);

        $radius = (int) ($data['radius_meters'] ?? 5);
        $data['radius_meters'] = max(1, $radius);
        $data['geofence_radius_m'] = $data['radius_meters'];

        return $data;
    }

    private function shouldValidateUniqueExternalId(mixed $externalId, ?int $id, bool $enforceUniqueExternalId): bool
    {
        if (! $enforceUniqueExternalId || $externalId === null || $externalId === '') {
            return false;
        }

        if ($id === null) {
            return true;
        }

        $currentExternalId = $this->normalizeExternalId(Outlet::query()->whereKey($id)->value('external_id'));

        return $currentExternalId !== $externalId;
    }

    private function normalizeExternalId(mixed $value): ?string
    {
        $externalId = trim((string) $value);

        return $externalId === '' ? null : $externalId;
    }

    private function normalizeImportRow(array $row): array
    {
        foreach (['work_start_time', 'work_end_time'] as $timeKey) {
            if (array_key_exists($timeKey, $row)) {
                $row[$timeKey] = $this->normalizeTimeValue($row[$timeKey]);
            }
        }

        return $row;
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_int($value) || is_float($value)) {
            return $this->normalizeExcelNumericTime((float) $value);
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        if (is_numeric($stringValue)) {
            return $this->normalizeExcelNumericTime((float) $stringValue);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $stringValue) === 1) {
            return $stringValue;
        }

        if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $stringValue, $matches) === 1) {
            return $matches[1];
        }

        try {
            return now()->parse($stringValue)->format('H:i');
        } catch (\Throwable) {
            return $stringValue;
        }
    }

    private function normalizeExcelNumericTime(float $value): ?string
    {
        if ($value < 0) {
            return null;
        }

        try {
            return ExcelDate::excelToDateTimeObject($value)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function mergeImportedOutletPayload(Outlet $existing, array $payload, string $rawExternalId): array
    {
        foreach (['location', 'latitude', 'longitude', 'work_start_time', 'work_end_time'] as $optionalKey) {
            if (($payload[$optionalKey] ?? null) === null || $payload[$optionalKey] === '') {
                $payload[$optionalKey] = $existing->{$optionalKey};
            }
        }

        if ($rawExternalId === '' && empty($payload['external_id'])) {
            $payload['external_id'] = $existing->external_id;
        }

        return $payload;
    }

    private function externalIdHasDuplicateRows(string $rawExternalId): bool
    {
        return Outlet::query()
            ->where('external_id', $rawExternalId)
            ->limit(2)
            ->count() > 1;
    }

    private function validatedPermit(Request $request): array
    {
        return $request->validate([
            'permit_type' => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuer_name' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'status' => ['required', Rule::in(['active', 'draft', 'expired', 'revoked'])],
            'notes' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'attachments.*.mimes' => 'Lampiran izin outlet harus berupa PDF, JPG, JPEG, PNG, atau WEBP.',
            'attachments.*.max' => 'Ukuran lampiran izin outlet maksimal 4MB per file.',
        ]);
    }

    private function storePermitAttachments(OutletPermit $permit, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('outlet-permits/' . $permit->outlet_id . '/' . $permit->id, 'public');
            $permit->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $rows = $sheets[0] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['file' => 'File import kosong.']);
        }

        return $rows;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function extractCoordinatesFromReference(string $mapsReference, string $location): array
    {
        $candidates = array_filter([$mapsReference, $location]);

        foreach ($candidates as $candidate) {
            $coordinates = $this->extractCoordinatesFromText($candidate);
            if ($coordinates !== null) {
                return $coordinates;
            }
        }

        return [null, null];
    }

    private function extractCoordinatesFromText(string $text): ?array
    {
        $patterns = [
            '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/\b(-?\d{1,2}\.\d{4,})\s*,\s*(-?\d{1,3}\.\d{4,})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $text, $matches)) {
                continue;
            }

            $lat = (float) $matches[1];
            $lng = (float) $matches[2];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            return [$lat, $lng];
        }

        return null;
    }

    private function getPerPage(int $default = 20): int
    {
        $perPage = (int) request('per_page', $default);
        return in_array($perPage, [10, 15, 20, 50, 100, 200, 9999]) ? $perPage : $default;
    }
}
