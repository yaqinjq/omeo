<?php

namespace App\Models\Sport;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use SoftDeletes;

    protected $table = 'sport_events';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REGISTRATION_CLOSED = 'registration_closed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ATTENDANCE_OPEN = 'attendance_open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Timezone bisnis OMEO untuk input/tampilan jam event — dipakai HANYA di
     * modul Sport (lihat EventController::jakartaInputToAppTimezone() untuk
     * konversi input, dan README_SPORT_MODULE.md bagian "Timezone" untuk
     * alasan ini tidak mengubah config('app.timezone') global atau modul
     * lain). Sama persis dengan konstanta fallback 'Asia/Jakarta' yang
     * sudah dipakai AttendanceService/Walkin — bukan nilai baru yang
     * ditebak, cuma dipakai scoped ke Sport.
     */
    public const BUSINESS_TIMEZONE = 'Asia/Jakarta';

    /**
     * Jendela konfirmasi kehadiran dibuka setelah event selesai, selama ini
     * banyak menit — "sekitar 1 jam" per spesifikasi MVP. Belum ada
     * keputusan owner soal toleransi/konfigurasi ini, jadi nilainya hardcode
     * ke 60 menit (sesuai angka yang disebut spesifikasi) sampai ada
     * keputusan untuk membuatnya configurable.
     */
    public const ATTENDANCE_WINDOW_MINUTES = 60;

    protected $fillable = [
        'sport',
        'title',
        'location',
        'event_date',
        'start_at',
        'end_at',
        'has_coach',
        'coach_name',
        'capacity',
        'coordinator_user_id',
        'status',
        'share_token',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'has_coach' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'sport_event_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'sport_event_id');
    }

    public function nonMkoAttendances(): HasMany
    {
        return $this->hasMany(NonMkoAttendance::class, 'sport_event_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'sport_event_id');
    }

    /**
     * Event ber-coach wajib punya kapasitas positif sebelum dipublish.
     * Event tanpa coach tidak diblokir kapasitas (tidak ada kuota umum).
     */
    public function canPublish(): bool
    {
        if (! $this->has_coach) {
            return true;
        }

        return $this->capacity !== null && $this->capacity > 0;
    }

    /**
     * Pendaftaran ditutup TEPAT pada waktu mulai event (bukan waktu selesai).
     */
    public function isRegistrationOpen(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return Carbon::now()->lt($this->start_at);
    }

    /**
     * Konfirmasi kehadiran dibuka setelah event selesai, ditutup lagi
     * setelah ATTENDANCE_WINDOW_MINUTES — dihitung dari waktu server, bukan
     * dari status kolom (supaya tidak tergantung cron job yang menjalankan
     * transisi status otomatis, yang belum ada di MVP ini).
     */
    public function isAttendanceWindowOpen(): bool
    {
        if (in_array($this->status, [self::STATUS_CANCELLED], true)) {
            return false;
        }

        $now = Carbon::now();

        return $now->gte($this->end_at)
            && $now->lt($this->end_at->copy()->addMinutes(self::ATTENDANCE_WINDOW_MINUTES));
    }

    /**
     * Sisa kuota — null berarti "tidak ada batas umum" (event tanpa coach).
     * Menghitung registrasi yang masih aktif (registered ATAU sedang
     * mengajukan pembatalan — slot baru benar-benar lepas setelah
     * Koordinator MENYETUJUI pembatalan, bukan saat baru diajukan).
     */
    public function remainingCapacity(): ?int
    {
        if (! $this->has_coach) {
            return null;
        }

        $taken = $this->registrations()
            ->whereIn('status', [Registration::STATUS_REGISTERED, Registration::STATUS_CANCELLATION_REQUESTED])
            ->count();

        return max(0, (int) $this->capacity - $taken);
    }

    public function isFull(): bool
    {
        $remaining = $this->remainingCapacity();

        return $remaining !== null && $remaining <= 0;
    }
}
