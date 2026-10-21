<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\Event;
use App\Models\Sport\NonMkoAttendance;
use App\Models\Sport\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Input manual partisipasi Non-MKO — HANYA Admin Finance (lihat route
 * middleware role:admin,finance di routes/sport.php). Tidak ada alur
 * self-service untuk Non-MKO (tidak punya akun login), jadi ini SATU-SATUNYA
 * cara partisipasi mereka tercatat, bukan fitur "backdate" (lihat README
 * modul Sport bagian Backdate utk perbedaannya).
 */
class NonMkoAttendanceController extends Controller
{
    public function forEvent(Event $event): View
    {
        $event->load(['nonMkoAttendances.participant']);
        $participants = Participant::orderBy('full_name')->get();

        return view('sport.admin.events.non_mko', ['event' => $event, 'participants' => $participants]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'participant_id' => ['nullable', 'integer', 'exists:sport_participants,id'],
            'full_name' => ['required_without:participant_id', 'nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'attended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $event, $request) {
            $participant = $data['participant_id'] ?? null
                ? Participant::findOrFail($data['participant_id'])
                : Participant::create([
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'] ?? null,
                    'external_reference' => $data['external_reference'] ?? null,
                    'created_by_user_id' => $request->user()->id,
                ]);

            NonMkoAttendance::updateOrCreate(
                ['sport_event_id' => $event->id, 'sport_participant_id' => $participant->id],
                [
                    'recorded_by_user_id' => $request->user()->id,
                    'attended_at' => $data['attended_at'] ?? now(),
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });

        return back()->with('success', 'Partisipasi peserta Non-MKO berhasil dicatat.');
    }

    // Sengaja TIDAK ADA method destroy()/hard-delete di sini. Keputusan
    // retensi terbaru: partisipasi Non-MKO adalah riwayat (satu-satunya
    // record kehadiran mereka, tidak ada self-service), jadi tidak boleh
    // bisa dihapus lewat UI/API apa pun. Koreksi data dilakukan dengan
    // menambah catatan baru (lihat `notes`), bukan menghapus record lama.
}
