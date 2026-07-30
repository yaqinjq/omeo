<?php

namespace App\Http\Controllers;

use App\Models\CareerDepartment;
use App\Models\CareerPost;
use App\Models\HrTeamMember;
use App\Models\LandingPageSetting;
use App\Models\Walkin\Event as WalkinEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $setting = LandingPageSetting::current();
        $hrTeamMembers = $this->hrTeamMembers();
        $careerDepartments = $this->careerDepartments();
        $featuredCareers = $this->featuredCareers();
        $featuredWalkIns = $this->featuredWalkIns();

        return view('landing', [
            'landingSetting' => $setting,
            'hrTeamMembers' => $hrTeamMembers,
            'careerDepartments' => $careerDepartments,
            'featuredCareers' => $featuredCareers,
            'featuredWalkIns' => $featuredWalkIns,
            'areaLocations' => $this->areaLocations(),
        ]);
    }

    private function hrTeamMembers(): Collection
    {
        if (! Schema::hasTable((new HrTeamMember())->getTable())) {
            return collect();
        }

        return HrTeamMember::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function careerDepartments(): Collection
    {
        if (
            ! Schema::hasTable((new CareerDepartment())->getTable())
            || ! Schema::hasTable((new CareerPost())->getTable())
        ) {
            return collect();
        }

        return CareerDepartment::query()
            ->active()
            ->whereHas('posts', fn ($query) => $query->published())
            ->with(['posts' => fn ($query) => $query->published()->latest('published_at')->latest('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function featuredCareers(): Collection
    {
        if (! Schema::hasTable((new CareerPost())->getTable())) {
            return collect();
        }

        return CareerPost::query()
            ->published()
            ->with('department')
            ->latest('published_at')
            ->latest('id')
            ->limit(6)
            ->get();
    }

    private function featuredWalkIns(): Collection
    {
        if (! Schema::hasTable('walkin_events')) {
            return collect();
        }

        $now = now()->setTimezone('Asia/Jakarta');

        return WalkinEvent::query()
            ->whereIn('status', ['published', 'ongoing'])
            ->where(function ($q) use ($now) {
                $q->whereNull('event_end_datetime')
                  ->orWhere('event_end_datetime', '>=', $now);
            })
            ->orderBy('event_start_datetime')
            ->limit(3)
            ->get();
    }

    private function areaLocations(): array
    {
        return [
            ['name' => 'Surabaya', 'province' => 'Jawa Timur', 'x' => 43, 'y' => 68],
            ['name' => 'Pasuruan', 'province' => 'Jawa Timur', 'x' => 47, 'y' => 68],
            ['name' => 'Semarang', 'province' => 'Jawa Tengah', 'x' => 38, 'y' => 62],
            ['name' => 'Depok', 'province' => 'Jawa Barat', 'x' => 31, 'y' => 64],
            ['name' => 'Malang', 'province' => 'Jawa Timur', 'x' => 46, 'y' => 71],
            ['name' => 'Bangil/Sukodono', 'province' => 'Jawa Timur', 'x' => 45, 'y' => 68],
            ['name' => 'Samarinda', 'province' => 'Kalimantan Timur', 'x' => 58, 'y' => 43],
            ['name' => 'Lombok', 'province' => 'Nusa Tenggara Barat', 'x' => 54, 'y' => 73],
            ['name' => 'Sidoarjo', 'province' => 'Jawa Timur', 'x' => 43, 'y' => 69],
            ['name' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'x' => 37, 'y' => 68],
            ['name' => 'Tanjung Duren', 'province' => 'DKI Jakarta', 'x' => 29, 'y' => 61],
            ['name' => 'Makassar', 'province' => 'Sulawesi Selatan', 'x' => 67, 'y' => 65],
            ['name' => 'Pandaan', 'province' => 'Jawa Timur', 'x' => 46, 'y' => 69],
            ['name' => 'Solo', 'province' => 'Jawa Tengah', 'x' => 39, 'y' => 66],
            ['name' => 'Bendungan Hilir', 'province' => 'DKI Jakarta', 'x' => 30, 'y' => 62],
            ['name' => 'Palembang', 'province' => 'Sumatera Selatan', 'x' => 22, 'y' => 55],
        ];
    }
}
