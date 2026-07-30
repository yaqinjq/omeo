<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class OutletApiController extends Controller
{
    private const SCHEMA_VERSION = '2026-06-03';

    private const SYNC_KEY_STRATEGY = [
        'preferred' => 'external_id',
        'fallback' => 'id',
        'note' => 'Store both external_id and id in the consuming app. Use external_id only when it is present and unique.',
    ];

    public function index(Request $request): JsonResponse
    {
        $outlets = $this->buildOutletPaginator($request);

        return response()->json($this->paginatedOutletResponse($outlets, $request));
    }

    public function branches(Request $request): JsonResponse
    {
        $outlets = $this->buildOutletPaginator($request, false);
        $branchData = collect($outlets->items())
            ->map(fn (array $outlet) => $this->transformBranchPayload($outlet))
            ->values()
            ->all();

        return response()->json([
            'message' => 'OK',
            'data' => $branchData,
            'branches' => $branchData,
            'current_page' => $outlets->currentPage(),
            'per_page' => $outlets->perPage(),
            'total' => $outlets->total(),
            'last_page' => $outlets->lastPage(),
            'next_page_url' => $outlets->nextPageUrl(),
            'prev_page_url' => $outlets->previousPageUrl(),
        ]);
    }

    public function show(Outlet $outlet): JsonResponse
    {
        return response()->json([
            'message' => 'OK',
            'schema_version' => self::SCHEMA_VERSION,
            'sync_key_strategy' => self::SYNC_KEY_STRATEGY,
            'data' => $this->transformOutlet($outlet),
        ]);
    }

    private function buildOutletPaginator(Request $request, bool $stableContract = true): LengthAwarePaginator
    {
        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Outlet::query()
            ->orderBy('name')
            ->orderBy('id');

        $brandName = trim((string) ($validated['brand_name'] ?? ''));
        if ($brandName !== '') {
            $query->where('brand_name', $brandName);
        }

        $externalId = trim((string) ($validated['external_id'] ?? ''));
        if ($externalId !== '') {
            $query->where('external_id', $externalId);
        }

        $updatedSince = $validated['updated_since'] ?? null;
        if ($updatedSince !== null) {
            $query->where('updated_at', '>=', CarbonImmutable::parse((string) $updatedSince));
        }

        $perPage = (int) ($validated['per_page'] ?? 25);

        return $query->paginate($perPage)->through(
            fn (Outlet $outlet) => $stableContract
                ? $this->transformOutlet($outlet)
                : $this->transformCompatibilityOutlet($outlet)
        );
    }

    private function transformOutlet(Outlet $outlet): array
    {
        return [
            'id' => (int) $outlet->id,
            'external_id' => $this->nullableString($outlet->external_id),
            'name' => (string) $outlet->name,
            'brand_name' => $this->nullableString($outlet->brand_name),
            'location' => $this->nullableString($outlet->location),
            'latitude' => $outlet->latitude,
            'longitude' => $outlet->longitude,
            'radius_meters' => $this->nullableInt($outlet->radius_meters ?? $outlet->geofence_radius_m),
            'timezone' => $this->nullableString($outlet->timezone),
            'work_start_time' => $this->formatTime($outlet->work_start_time),
            'work_end_time' => $this->formatTime($outlet->work_end_time),
            'updated_at' => optional($outlet->updated_at)->toISOString(),
        ];
    }

    private function transformCompatibilityOutlet(Outlet $outlet): array
    {
        return [
            'id' => (int) $outlet->id,
            'name' => (string) $outlet->name,
            'brand_name' => $outlet->brand_name,
            'external_id' => $outlet->external_id,
            'location' => $outlet->location,
            'latitude' => $outlet->latitude,
            'longitude' => $outlet->longitude,
            'radius_meters' => $outlet->radius_meters ?? $outlet->geofence_radius_m,
            'timezone' => $outlet->timezone,
            'work_start_time' => $outlet->work_start_time,
            'work_end_time' => $outlet->work_end_time,
            'updated_at' => optional($outlet->updated_at)->toISOString(),
        ];
    }

    private function paginatedOutletResponse(LengthAwarePaginator $outlets, Request $request): array
    {
        return array_merge([
            'message' => 'OK',
            'schema_version' => self::SCHEMA_VERSION,
            'sync_key_strategy' => self::SYNC_KEY_STRATEGY,
        ], $outlets->toArray(), [
            'filters' => [
                'brand_name' => $this->requestString($request, 'brand_name'),
                'external_id' => $this->requestString($request, 'external_id'),
                'updated_since' => $request->filled('updated_since')
                    ? CarbonImmutable::parse((string) $request->query('updated_since'))->toISOString()
                    : null,
            ],
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        if (preg_match('/^(\d{2}:\d{2})/', $string, $matches) === 1) {
            return $matches[1];
        }

        return $string;
    }

    private function requestString(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value === '' ? null : $value;
    }

    private function transformBranchPayload(array $outlet): array
    {
        $branchCode = trim((string) ($outlet['external_id'] ?? ''));
        if ($branchCode === '') {
            $branchCode = (string) ($outlet['id'] ?? '');
        }

        return array_merge($outlet, [
            'branch_id' => $outlet['id'] ?? null,
            'branch_name' => $outlet['name'] ?? null,
            'branch_code' => $branchCode,
            'code' => $branchCode,
            'title' => $outlet['name'] ?? null,
        ]);
    }
}
