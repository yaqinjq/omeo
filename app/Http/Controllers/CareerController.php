<?php

namespace App\Http\Controllers;

use App\Models\CareerDepartment;
use App\Models\CareerPost;
use App\Models\LandingPageSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(Request $request): View
    {
        $posts = CareerPost::query()
            ->published()
            ->with('department')
            ->when($request->filled('department'), function ($query) use ($request): void {
                $query->whereHas('department', fn ($builder) => $builder->where('slug', $request->string('department')));
            })
            ->when($request->filled('location'), fn ($query) => $query->where('location', $request->string('location')))
            ->when($request->filled('type'), fn ($query) => $query->where('employment_type', $request->string('type')))
            ->latest('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('careers.index', [
            'landingSetting' => LandingPageSetting::current(),
            'posts' => $posts,
            'departments' => CareerDepartment::query()
                ->active()
                ->whereHas('posts', fn ($query) => $query->published())
                ->orderBy('name')
                ->get(),
            'locations' => CareerPost::query()
                ->published()
                ->whereNotNull('location')
                ->distinct()
                ->orderBy('location')
                ->pluck('location'),
            'types' => CareerPost::EMPLOYMENT_TYPES,
        ]);
    }

    public function show(CareerPost $career): View
    {
        abort_unless($career->newQuery()->whereKey($career->id)->published()->exists(), 404);

        $career->load('department');

        return view('careers.show', [
            'landingSetting' => LandingPageSetting::current(),
            'post' => $career,
        ]);
    }
}
