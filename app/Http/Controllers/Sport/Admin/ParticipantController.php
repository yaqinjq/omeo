<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar/kelola profil peserta Non-MKO. Hanya Admin Finance. Non-MKO
 * BUKAN employee HRD — model Participant sama sekali tidak menyentuh tabel
 * employees (lihat Spesifikasi v1.1 Section 2 & 9).
 */
class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $participants = Participant::query()
            ->withCount('attendances')
            ->when($request->filled('q'), fn ($q) => $q->where('full_name', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('sport.admin.participants.index', ['participants' => $participants, 'q' => $request->input('q')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['created_by_user_id'] = $request->user()->id;

        Participant::create($data);

        return back()->with('success', 'Peserta Non-MKO baru tersimpan.');
    }
}
