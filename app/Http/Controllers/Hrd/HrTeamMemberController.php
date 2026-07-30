<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\HrTeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrTeamMemberController extends Controller
{
    public function index(): View
    {
        return view('hrd.hr_team.index', [
            'members' => HrTeamMember::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('hrd.hr_team.form', [
            'member' => new HrTeamMember(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('hr-team', 'public');
        }

        HrTeamMember::query()->create($data);

        return redirect()->route('dashboard.hr-team.index')->with('success', 'Anggota Tim HR berhasil ditambahkan.');
    }

    public function edit(HrTeamMember $hrTeamMember): View
    {
        return view('hrd.hr_team.form', [
            'member' => $hrTeamMember,
        ]);
    }

    public function update(Request $request, HrTeamMember $hrTeamMember): RedirectResponse
    {
        $data = $this->validated($request, $hrTeamMember);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('hr-team', 'public');
        }

        $hrTeamMember->update($data);

        return redirect()->route('dashboard.hr-team.index')->with('success', 'Anggota Tim HR berhasil diperbarui.');
    }

    public function destroy(HrTeamMember $hrTeamMember): RedirectResponse
    {
        $hrTeamMember->delete();

        return back()->with('success', 'Anggota Tim HR berhasil dihapus.');
    }

    private function validated(Request $request, ?HrTeamMember $member = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'position' => ['required', 'string', 'max:160'],
            'company_email' => ['required', 'email', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]) + [
            'sort_order' => 0,
            'is_active' => false,
        ];
    }
}
