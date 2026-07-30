<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class ApplicantProfile extends Model
{
    use SoftDeletes {
        bootSoftDeletes as private bootSoftDeletesTrait;
        initializeSoftDeletes as private initializeSoftDeletesTrait;
        performDeleteOnModel as private performDeleteOnModelTrait;
        restore as private restoreTrait;
        restoreQuietly as private restoreQuietlyTrait;
        trashed as private trashedTrait;
    }

    public const SECTION_PERSONAL = 'personal';
    public const SECTION_FAMILY = 'family';
    public const SECTION_EDUCATION = 'education';
    public const SECTION_EXPERIENCE = 'experience';
    public const SECTION_MEDICAL = 'medical';
    public const SECTION_SOCIAL = 'social';

    public const GOVERNANCE_STATUS_ACTIVE = 'active';
    public const GOVERNANCE_STATUS_REJECTED = 'rejected';
    public const GOVERNANCE_STATUS_BLACKLISTED = 'blacklisted';
    public const GOVERNANCE_STATUS_ARCHIVED = 'archived';

    public const GOVERNANCE_STATUS_LABELS = [
        self::GOVERNANCE_STATUS_ACTIVE => 'Active',
        self::GOVERNANCE_STATUS_REJECTED => 'Rejected',
        self::GOVERNANCE_STATUS_BLACKLISTED => 'Blacklisted',
        self::GOVERNANCE_STATUS_ARCHIVED => 'Archived',
    ];

    public const TALENT_POOL_STAGE_INCOMPLETE = 'incomplete';
    public const TALENT_POOL_STAGE_UNVERIFIED = 'unverified';
    public const TALENT_POOL_STAGE_VERIFIED = 'verified';

    public const TALENT_POOL_STAGE_LABELS = [
        self::TALENT_POOL_STAGE_INCOMPLETE => 'Belum Lengkap',
        self::TALENT_POOL_STAGE_UNVERIFIED => 'Complete-Unverified',
        self::TALENT_POOL_STAGE_VERIFIED => 'Verified/Shortlisted',
    ];

    private const SECTION_META = [
        self::SECTION_PERSONAL => ['label' => 'Data Pribadi & Dokumen', 'step' => 1],
        self::SECTION_FAMILY => ['label' => 'Alamat, Keluarga & Kontak Darurat', 'step' => 2],
        self::SECTION_EDUCATION => ['label' => 'Pendidikan', 'step' => 3],
        self::SECTION_EXPERIENCE => ['label' => 'Preferensi & Pengalaman', 'step' => 4],
        self::SECTION_MEDICAL => ['label' => 'Kesehatan', 'step' => 5],
        self::SECTION_SOCIAL => ['label' => 'Finalisasi & Dokumen Lanjutan', 'step' => 6],
    ];

    private const PERSONAL_DOCUMENT_KEYS = [
        'photo_path' => ['photo_path', 'photo_ktp_path', 'photo_file_path', 'pas_foto_path'],
        'ktp_path' => ['ktp_path', 'scan_ktp_path', 'ktp_file_path', 'scan_ktp_file_path'],
        'cv_path' => ['cv_path', 'cv_file_path', 'resume_path'],
        'signature_path' => ['signature_path'],
        'skck_latest_path' => ['skck_latest_path'],
    ];

    private static array $columnSupportCache = [];
    private static array $tableSupportCache = [];

    protected $table = 'applicant_profiles';

    protected $fillable = [
        'user_id',
        'personal_json', 'family_json', 'address_json', 'education_json',
        'language_json', 'work_json', 'organization_json', 'course_json',
        'medical_json', 'social_json', 'completed_at',
        'governance_status', 'governance_reason', 'governed_at',
        'rejected_at', 'blacklisted_at', 'archived_at',
        'governed_by', 'governance_meta',
    ];

    protected $casts = [
        'personal_json' => 'array', 'family_json' => 'array', 'address_json' => 'array',
        'education_json' => 'array', 'language_json' => 'array', 'work_json' => 'array',
        'organization_json' => 'array', 'course_json' => 'array', 'medical_json' => 'array',
        'social_json' => 'array', 'completed_at' => 'datetime',
        'governed_at' => 'datetime', 'rejected_at' => 'datetime',
        'blacklisted_at' => 'datetime', 'archived_at' => 'datetime',
        'governance_meta' => 'array', 'deleted_at' => 'datetime',
    ];

    protected static function bootSoftDeletes(): void
    {
        if (static::supportsSoftDeleteColumn()) {
            static::bootSoftDeletesTrait();
        }
    }

    public function initializeSoftDeletes(): void
    {
        if (static::supportsSoftDeleteColumn()) {
            $this->initializeSoftDeletesTrait();
        }
    }

    public static function supportsSoftDeleteColumn(): bool
    {
        return static::supportsColumn('deleted_at');
    }

    public static function supportsGovernanceStatusColumn(): bool
    {
        return static::supportsColumn('governance_status');
    }

    public static function supportsGovernancePersistence(): bool
    {
        foreach (['governance_status', 'governance_reason', 'governed_at', 'rejected_at', 'blacklisted_at', 'archived_at', 'governed_by', 'governance_meta'] as $column) {
            if (! static::supportsColumn($column)) {
                return false;
            }
        }

        return true;
    }

    public static function supportsAuditLogTable(): bool
    {
        $cacheKey = static::class . '|applicant_profile_activity_logs';

        return self::$tableSupportCache[$cacheKey] ??= Schema::hasTable('applicant_profile_activity_logs');
    }

    public function scopeWithTrashed(Builder $query, bool $withTrashed = true): Builder
    {
        if (! static::supportsSoftDeleteColumn()) {
            return $query;
        }

        if (! $withTrashed) {
            return $query->withoutTrashed();
        }

        return $query->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function scopeWithoutTrashed(Builder $query): Builder
    {
        if (! static::supportsSoftDeleteColumn()) {
            return $query;
        }

        return $query->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNull($this->qualifyColumn($this->getDeletedAtColumn()));
    }

    public function scopeOnlyTrashed(Builder $query): Builder
    {
        if (! static::supportsSoftDeleteColumn()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->qualifyColumn($this->getDeletedAtColumn()));
    }

    public function trashed(): bool
    {
        return static::supportsSoftDeleteColumn() ? $this->trashedTrait() : false;
    }

    public function restore()
    {
        return static::supportsSoftDeleteColumn() ? $this->restoreTrait() : false;
    }

    public function restoreQuietly()
    {
        return static::supportsSoftDeleteColumn() ? $this->restoreQuietlyTrait() : false;
    }

    protected function performDeleteOnModel()
    {
        return static::supportsSoftDeleteColumn()
            ? $this->performDeleteOnModelTrait()
            : parent::performDeleteOnModel();
    }

    public function user() { return $this->belongsTo(User::class); }
    public function governedBy() { return $this->belongsTo(User::class, 'governed_by'); }
    public function activityLogs() { return $this->hasMany(ApplicantProfileActivityLog::class)->latest('id'); }

    public function getFullNameAttribute() { return data_get($this->personal_json, 'full_name', ''); }
    public function getKtpNumberAttribute() { return data_get($this->personal_json, 'ktp_number', ''); }
    public function getPlaceOfBirthAttribute() { return data_get($this->personal_json, 'place_of_birth', ''); }
    public function getDateOfBirthAttribute() { return data_get($this->personal_json, 'date_of_birth', ''); }
    public function getTimeOfBirthAttribute() { return data_get($this->personal_json, 'time_of_birth', ''); }
    public function getGenderAttribute() { return data_get($this->personal_json, 'gender', ''); }
    public function getReligionAttribute() { return data_get($this->personal_json, 'religion', ''); }
    public function getBloodTypeAttribute() { return data_get($this->personal_json, 'blood_type', ''); }
    public function getMaritalStatusAttribute() { return data_get($this->personal_json, 'marital_status', ''); }
    public function getMarriageDateAttribute() { return data_get($this->personal_json, 'marriage_date', ''); }
    public function getWhatsappAttribute() { return data_get($this->personal_json, 'whatsapp', ''); }
    public function getPhoneNumberAttribute() { return data_get($this->personal_json, 'phone_number', ''); }
    public function getSalaryExpectationAttribute() { return data_get($this->personal_json, 'salary_expectation'); }
    public function getReferenceContactsAttribute() { return data_get($this->personal_json, 'reference_contacts', []); }
    public function getEmergencyContactsAttribute() { return data_get($this->personal_json, 'emergency_contacts', []); }
    public function getPhotoPathAttribute() { return $this->resolvePersonalDocumentPath('photo_path'); }
    public function getCvPathAttribute() { return $this->resolvePersonalDocumentPath('cv_path'); }
    public function getKtpPathAttribute() { return $this->resolvePersonalDocumentPath('ktp_path'); }
    public function getSignaturePathAttribute() { return $this->resolvePersonalDocumentPath('signature_path'); }
    public function getSkckLatestPathAttribute() { return $this->resolvePersonalDocumentPath('skck_latest_path'); }
    public function getAppliedPositionIdAttribute() { return $this->resolveNullableInteger(['applied_position_id']); }
    public function getAppliedPositionNameAttribute() { return $this->resolvePreferredString(['applied_position_name', 'applied_position']); }
    public function getAppliedDepartmentIdAttribute() { return $this->resolveNullableInteger(['applied_department_id']); }
    public function getAppliedDepartmentNameAttribute() { return $this->resolvePreferredString(['applied_department_name', 'preferred_department', 'applied_department']); }
    public function getAppliedOutletIdAttribute() { return $this->resolveNullableInteger(['applied_outlet_id']); }
    public function getAppliedOutletNameAttribute() { return $this->resolvePreferredString(['applied_outlet_name', 'preferred_outlet', 'applied_outlet']); }

    public function getKtpAddressAttribute() { return data_get($this->address_json, 'ktp_address', ''); }
    public function getKtpProvinceAttribute() { return data_get($this->address_json, 'ktp_province', ''); }
    public function getKtpCityAttribute() { return data_get($this->address_json, 'ktp_city', ''); }
    public function getDomicileAddressAttribute() { return data_get($this->address_json, 'domicile_address', ''); }

    public function getFamiliesAttribute() { return is_array($this->family_json) ? $this->family_json : []; }
    public function getEducationsAttribute() { return is_array($this->education_json) ? $this->education_json : []; }
    public function getLanguagesAttribute() { return is_array($this->language_json) ? $this->language_json : []; }
    public function getCoursesAttribute() { return is_array($this->course_json) ? $this->course_json : []; }
    public function getOrganizationsAttribute() { return is_array($this->organization_json) ? $this->organization_json : []; }
    public function getWorkExperiencesAttribute() { return is_array($this->work_json) ? $this->work_json : []; }
    public function getMedicalHistoriesAttribute() { return data_get($this->medical_json, 'histories', is_array($this->medical_json) ? $this->medical_json : []); }
    public function getSocialMediasAttribute() { return is_array($this->social_json) ? $this->social_json : []; }

    public function getIsCompleteAttribute() { return $this->completed_at !== null; }

    public function isGovernanceActive(): bool
    {
        if ($this->currentGovernanceStatus() !== self::GOVERNANCE_STATUS_ACTIVE) {
            return false;
        }

        if (static::supportsSoftDeleteColumn() && $this->deleted_at !== null) {
            return false;
        }

        return true;
    }

    public function talentPoolStage(bool $hasCandidate = false): string
    {
        if (! $this->isGovernanceActive()) {
            return $this->currentGovernanceStatus();
        }

        if (! $this->is_complete) {
            return self::TALENT_POOL_STAGE_INCOMPLETE;
        }

        return $hasCandidate ? self::TALENT_POOL_STAGE_VERIFIED : self::TALENT_POOL_STAGE_UNVERIFIED;
    }

    public function governanceStatusLabel(): string
    {
        $status = $this->currentGovernanceStatus();
        return self::GOVERNANCE_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function isSectionComplete(string $section): bool
    {
        $missing = $this->getMissingFields();
        return empty($missing[$section]['fields'] ?? []);
    }

    public function isProfileComplete(): bool
    {
        return empty($this->getMissingFields());
    }

    public function getMissingFields(): array
    {
        $missing = [];
        $personal = $this->normalizedPersonalJson();
        $address = is_array($this->address_json) ? $this->address_json : [];
        $medical = is_array($this->medical_json) ? $this->medical_json : [];

        foreach ([
            'full_name' => 'Nama lengkap',
            'ktp_number' => 'NIK',
            'place_of_birth' => 'Tempat lahir',
            'date_of_birth' => 'Tanggal lahir',
            'time_of_birth' => 'Jam lahir',
            'gender' => 'Jenis kelamin',
            'religion' => 'Agama',
            'marital_status' => 'Status pernikahan',
            'whatsapp' => 'Nomor WhatsApp aktif',
            'phone_number' => 'Nomor telepon / HP aktif',
            'applied_position_name' => 'Posisi / jabatan yang dilamar',
            'salary_expectation' => 'Ekspektasi gaji',
            'willing_out_of_town' => 'Kesediaan luar kota',
            'willing_outside_java' => 'Kesediaan luar Jawa',
            'willing_shift' => 'Kesediaan shift',
            'willing_overtime' => 'Kesediaan lembur',
            'is_smoker' => 'Status merokok',
            'has_computer_skill' => 'Keahlian komputer',
            'wears_glasses' => 'Status kacamata',
            'join_reason' => 'Alasan bergabung',
            'company_relation_note' => 'Relasi di perusahaan',
            'career_goal' => 'Target karir',
            'available_start_date' => 'Tanggal siap bergabung',
            'honesty_statement' => 'Pernyataan kejujuran',
        ] as $key => $label) {
            $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) data_get($personal, $key))), $label);
        }

        if (trim((string) data_get($personal, 'wears_glasses')) === 'Ya') {
            $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) data_get($personal, 'glasses_right_eye'))), 'Detail ukuran mata kanan');
            $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) data_get($personal, 'glasses_left_eye'))), 'Detail ukuran mata kiri');
        }

        $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) $this->resolvePersonalDocumentPath('photo_path', $personal))), 'Pas foto');
        $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) $this->resolvePersonalDocumentPath('ktp_path', $personal))), 'Scan KTP');
        $this->pushMissing($missing, self::SECTION_PERSONAL, empty(trim((string) $this->resolvePersonalDocumentPath('cv_path', $personal))), 'CV PDF');

        foreach ([
            'ktp_address' => 'Alamat KTP',
            'ktp_rt' => 'RT KTP',
            'ktp_rw' => 'RW KTP',
            'ktp_kelurahan' => 'Kelurahan KTP',
            'ktp_kecamatan' => 'Kecamatan KTP',
            'ktp_city' => 'Kota/Kabupaten KTP',
            'domicile_address' => 'Alamat domisili',
            'domicile_rt' => 'RT domisili',
            'domicile_rw' => 'RW domisili',
            'domicile_kelurahan' => 'Kelurahan domisili',
            'domicile_kecamatan' => 'Kecamatan domisili',
            'domicile_city' => 'Kota/Kabupaten domisili',
        ] as $key => $label) {
            $this->pushMissing($missing, self::SECTION_FAMILY, empty(trim((string) data_get($address, $key))), $label);
        }

        if (! $this->hasRequiredFamilyMembers()) {
            $this->pushMissing($missing, self::SECTION_FAMILY, true, 'Susunan keluarga wajib sesuai status pernikahan');
        }

        if ($this->countCompleteRows($this->emergency_contacts, ['name', 'relation', 'phone', 'address']) < 2) {
            $this->pushMissing($missing, self::SECTION_FAMILY, true, 'Kontak darurat minimal 2 baris lengkap');
        }

        if ($this->countCompleteRows($this->educations, ['level', 'school', 'major', 'year_in', 'year_out']) < 3) {
            $this->pushMissing($missing, self::SECTION_EDUCATION, true, 'Riwayat pendidikan minimal 3 baris lengkap');
        }

        if (! $this->hasCompleteRow($this->work_experiences, ['company', 'position', 'date_start', 'salary', 'reason'])) {
            $this->pushMissing($missing, self::SECTION_EXPERIENCE, true, 'Riwayat pekerjaan minimal 1 baris lengkap');
        }

        if ($this->countCompleteRows($this->reference_contacts, ['name', 'relation', 'company', 'phone']) < 2) {
            $this->pushMissing($missing, self::SECTION_EXPERIENCE, true, 'Kontak referensi minimal 2 baris lengkap');
        }

        if (! $this->hasCompleteRow($this->medical_histories, ['illness', 'year', 'hospitalized'])) {
            $this->pushMissing($missing, self::SECTION_MEDICAL, true, 'Riwayat penyakit minimal 1 baris lengkap');
        }

        foreach (['weight_kg' => 'Berat badan', 'height_cm' => 'Tinggi badan', 'had_accident' => 'Riwayat kecelakaan', 'police_record' => 'Riwayat kepolisian', 'psychology_test' => 'Riwayat psikotes'] as $key => $label) {
            $this->pushMissing($missing, self::SECTION_MEDICAL, empty(trim((string) data_get($medical, $key))), $label);
        }


        $this->pushMissing($missing, self::SECTION_SOCIAL, empty(trim((string) $this->resolvePersonalDocumentPath('signature_path', $personal))), 'Tanda tangan digital');

        return $missing;
    }

    public function getCompletionProgress(): array
    {
        $missing = $this->getMissingFields();
        $sections = [];
        $completed = 0;

        foreach (self::SECTION_META as $key => $meta) {
            $isComplete = empty($missing[$key]['fields'] ?? []);
            if ($isComplete) {
                $completed++;
            }

            $sections[] = [
                'key' => $key,
                'label' => $meta['label'],
                'step' => $meta['step'],
                'complete' => $isComplete,
            ];
        }

        $total = count(self::SECTION_META);

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? (int) floor(($completed / $total) * 100) : 0,
            'sections' => $sections,
        ];
    }

    public function normalizedPersonalJson(): array
    {
        $personal = is_array($this->personal_json) ? $this->personal_json : [];

        foreach (array_keys(self::PERSONAL_DOCUMENT_KEYS) as $canonicalKey) {
            $resolved = $this->resolvePersonalDocumentPath($canonicalKey, $personal);
            if ($resolved !== null && $resolved !== '') {
                $personal[$canonicalKey] = $resolved;
            }
        }

        return $personal;
    }

    public function syncDocumentAliases(array $personal): array
    {
        foreach (self::PERSONAL_DOCUMENT_KEYS as $canonicalKey => $aliases) {
            $resolved = $this->resolvePersonalDocumentPath($canonicalKey, $personal);
            foreach ($aliases as $alias) {
                if ($resolved === null || $resolved === '') {
                    unset($personal[$alias]);
                    continue;
                }
                $personal[$alias] = $resolved;
            }
        }

        return $personal;
    }

    private function resolvePreferredString(array $keys, ?string $fallback = null): string
    {
        $personal = is_array($this->personal_json) ? $this->personal_json : [];
        foreach ($keys as $key) {
            $value = trim((string) data_get($personal, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) ($fallback ?? ''));
    }

    private function resolveNullableInteger(array $keys): ?int
    {
        $personal = is_array($this->personal_json) ? $this->personal_json : [];
        foreach ($keys as $key) {
            $value = data_get($personal, $key);
            if ($value === null || $value === '') {
                continue;
            }
            return is_numeric($value) ? (int) $value : null;
        }

        return null;
    }

    private function currentGovernanceStatus(): string
    {
        if (! static::supportsGovernanceStatusColumn()) {
            return self::GOVERNANCE_STATUS_ACTIVE;
        }

        $status = (string) ($this->governance_status ?: self::GOVERNANCE_STATUS_ACTIVE);
        return $status !== '' ? $status : self::GOVERNANCE_STATUS_ACTIVE;
    }

    private static function supportsColumn(string $column): bool
    {
        $cacheKey = static::class . '|' . $column;
        return self::$columnSupportCache[$cacheKey] ??= Schema::hasColumn((new static())->getTable(), $column);
    }

    private function pushMissing(array &$missing, string $section, bool $condition, string $field): void
    {
        if (! $condition) {
            return;
        }

        if (! isset($missing[$section])) {
            $missing[$section] = [
                'label' => self::SECTION_META[$section]['label'],
                'step' => self::SECTION_META[$section]['step'],
                'fields' => [],
            ];
        }

        $missing[$section]['fields'][] = $field;
    }

    private function hasCompleteRow(mixed $rows, array $requiredKeys): bool
    {
        return $this->countCompleteRows($rows, $requiredKeys) > 0;
    }

    private function countCompleteRows(mixed $rows, array $requiredKeys): int
    {
        if (! is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hasAny = false;
            foreach ($row as $value) {
                if (is_array($value)) {
                    $flattened = collect($value)->flatten();
                    if ($flattened->contains(static fn ($item) => $item !== null && trim((string) $item) !== '')) {
                        $hasAny = true;
                        break;
                    }

                    continue;
                }

                if ($value !== null && trim((string) $value) !== '') {
                    $hasAny = true;
                    break;
                }
            }

            if (! $hasAny) {
                continue;
            }

            $complete = true;
            foreach ($requiredKeys as $key) {
                if (! array_key_exists($key, $row) || trim((string) $row[$key]) === '') {
                    $complete = false;
                    break;
                }
            }

            if ($complete) {
                $count++;
            }
        }

        return $count;
    }

    private function hasRequiredFamilyMembers(): bool
    {
        $families = is_array($this->families) ? $this->families : [];
        $relations = collect($families)
            ->map(fn ($row) => mb_strtolower(trim((string) data_get($row, 'relation', ''))))
            ->filter();

        if (! $relations->contains('ayah') || ! $relations->contains('ibu')) {
            return false;
        }

        return match (trim((string) $this->marital_status)) {
            'Menikah' => $relations->contains('suami') || $relations->contains('istri'),
            'Single', 'Duda', 'Janda' => true,
            default => false,
        };
    }

    private function resolvePersonalDocumentPath(string $canonicalKey, ?array $personal = null): ?string
    {
        $personal ??= is_array($this->personal_json) ? $this->personal_json : [];

        foreach (self::PERSONAL_DOCUMENT_KEYS[$canonicalKey] ?? [$canonicalKey] as $key) {
            $value = trim((string) data_get($personal, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}


