<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;

class ApiDocumentationController extends Controller
{
    public function index(): View
    {
        $allowedEmails = config('outlet_api.login.allowed_emails', []);
        $allowedRoles = config('outlet_api.login.allowed_roles', []);

        return view('settings.api-docs', [
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'outletApiDirectTokenConfigured' => trim((string) config('outlet_api.direct_token')) !== '',
            'outletApiLoginConfigured' => $allowedEmails !== [] || $allowedRoles !== [],
            'outletExternalIdStats' => $this->outletExternalIdStats(),
        ]);
    }

    private function outletExternalIdStats(): array
    {
        if (! Schema::hasTable('outlets')) {
            return [
                'available' => false,
                'total' => 0,
                'missing' => 0,
                'duplicate_groups' => 0,
                'duplicate_samples' => [],
            ];
        }

        $duplicateGroups = Outlet::query()
            ->selectRaw('external_id, COUNT(*) as aggregate_count')
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->groupBy('external_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('external_id')
            ->get();

        $duplicateSamples = $duplicateGroups
            ->take(5)
            ->map(fn (Outlet $outlet) => [
                'external_id' => (string) $outlet->external_id,
                'count' => (int) $outlet->aggregate_count,
            ])
            ->all();

        return [
            'available' => true,
            'total' => Outlet::query()->count(),
            'missing' => Outlet::query()
                ->where(fn ($query) => $query->whereNull('external_id')->orWhere('external_id', ''))
                ->count(),
            'duplicate_groups' => $duplicateGroups->count(),
            'duplicate_samples' => $duplicateSamples,
        ];
    }
}
