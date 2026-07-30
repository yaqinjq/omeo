<?php

namespace App\Models;

use App\Support\Rbac\PermissionMap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_APPLICANT = 'applicant';
    public const ROLE_CANDIDATE = 'candidate';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_PROBATION = 'probation';
    public const ROLE_HRD = 'hrd';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_ADMIN = 'admin';

    public const ALLOWED_ROLES = [
        self::ROLE_APPLICANT,
        self::ROLE_CANDIDATE,
        self::ROLE_EMPLOYEE,
        self::ROLE_PROBATION,
        self::ROLE_HRD,
        self::ROLE_MANAGER,
        self::ROLE_ADMIN,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'employee_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    private ?array $resolvedPermissionCodes = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function applicantProfile()
    {
        return $this->hasOne(ApplicantProfile::class);
    }

    public function candidate()
    {
        return $this->hasOne(Candidate::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function trainingTrainer()
    {
        return $this->hasOne(TrainingTrainer::class);
    }

    public function profileChangeRequests()
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function hasRole(array|string $roles): bool
    {
        $roleList = is_array($roles) ? $roles : explode(',', $roles);
        $allowed = array_values(array_filter(array_map(
            static fn ($role) => strtolower(trim((string) $role)),
            $roleList
        )));

        return in_array($this->normalizedRole(), $allowed, true);
    }

    public function isHrd(): bool
    {
        return $this->hasRole([self::ROLE_ADMIN, self::ROLE_HRD, self::ROLE_MANAGER]);
    }

    public function isSuperAdmin(): bool
    {
        if ((bool) ($this->is_super_admin ?? false)) {
            return true;
        }

        if (! Schema::hasTable('roles')) {
            return false;
        }

        $role = $this->relationLoaded('roleDefinition')
            ? $this->getRelation('roleDefinition')
            : $this->roleDefinition()->first();

        return (bool) ($role?->is_super_admin ?? false);
    }

    public function isApprovedTrainer(): bool
    {
        if (! Schema::hasTable('training_trainers')) {
            return false;
        }

        return TrainingTrainer::query()
            ->where('user_id', $this->id)
            ->where('status', TrainingTrainer::STATUS_ACTIVE)
            ->exists();
    }

    public function normalizedRole(): string
    {
        $role = strtolower(trim((string) ($this->role ?? '')));

        if ($role === '' || ! preg_match('/^[a-z0-9._-]+$/', $role)) {
            return self::ROLE_APPLICANT;
        }

        return $role;
    }

    public function permissionCodes(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        if (is_array($this->resolvedPermissionCodes)) {
            return $this->resolvedPermissionCodes;
        }

        $roleSlug = $this->normalizedRole();

        $role = null;
        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
            $permissionColumns = Schema::getColumnListing('permissions');
            $permissionSelect = ['permissions.id'];

            if (in_array('slug', $permissionColumns, true)) {
                $permissionSelect[] = 'permissions.slug';
            }

            if (in_array('code', $permissionColumns, true)) {
                $permissionSelect[] = 'permissions.code';
            }

            if (count($permissionSelect) > 1) {
                $role = $this->relationLoaded('roleDefinition')
                    ? $this->getRelation('roleDefinition')
                    : $this->roleDefinition()->with([
                        'permissions' => fn ($query) => $query->select($permissionSelect),
                    ])->first();
            }
        }

        if ($role) {
            $codes = $role->permissions
                ->pluck('slug')
                ->map(static fn ($code) => strtolower(trim((string) $code)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($codes !== []) {
                $this->resolvedPermissionCodes = $codes;

                return $this->resolvedPermissionCodes;
            }
        }

        $this->resolvedPermissionCodes = PermissionMap::defaultPermissionsForRole($roleSlug);

        return $this->resolvedPermissionCodes;
    }

    public function hasPermission(string $permission): bool
    {
        $permission = strtolower(trim($permission));
        if ($permission === '') {
            return false;
        }

        foreach ($this->permissionCodes() as $granted) {
            if ($granted === '*') {
                return true;
            }

            if (Str::is($granted, $permission) || Str::is($permission, $granted)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(array|string $permissions): bool
    {
        $list = is_array($permissions) ? $permissions : explode(',', $permissions);
        foreach ($list as $permission) {
            if ($this->hasPermission((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array|string $permissions): bool
    {
        $list = is_array($permissions) ? $permissions : explode(',', $permissions);
        $filtered = array_values(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            $list
        )));

        if ($filtered === []) {
            return true;
        }

        foreach ($filtered as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function canAccessRoute(?string $routeName): bool
    {
        $requiredPermissions = PermissionMap::requiredForRoute($routeName);
        if ($requiredPermissions === []) {
            return true;
        }

        return $this->hasAllPermissions($requiredPermissions);
    }

    public function resolveCandidate(): ?Candidate
    {
        $candidate = $this->candidate()->first();
        if ($candidate) {
            return $candidate;
        }

        $email = mb_strtolower(trim((string) $this->email));
        $profile = $this->applicantProfile()->first();
        $profileEmail = mb_strtolower(trim((string) data_get($profile?->personal_json, 'email', '')));
        $profileNik = trim((string) data_get($profile?->personal_json, 'ktp_number', ''));

        $query = Candidate::query();
        $hasCondition = false;

        if ($email !== '') {
            $query->orWhereRaw('LOWER(email) = ?', [$email]);
            $hasCondition = true;
        }

        if ($profileEmail !== '' && $profileEmail !== $email) {
            $query->orWhereRaw('LOWER(email) = ?', [$profileEmail]);
            $hasCondition = true;
        }

        if ($profileNik !== '') {
            $query->orWhere('nik', $profileNik);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            return null;
        }

        $candidate = $query->orderByDesc('id')->first();

        if ($candidate && (int) ($candidate->user_id ?? 0) !== (int) $this->id) {
            $candidate->update(['user_id' => $this->id]);
            $candidate->refresh();
        }

        return $candidate;
    }

    public static function roleLabels(): array
    {
        $labels = [
            self::ROLE_APPLICANT => 'Applicant',
            self::ROLE_CANDIDATE => 'Candidate',
            self::ROLE_EMPLOYEE => 'Employee',
            self::ROLE_PROBATION => 'Probation',
            self::ROLE_HRD => 'HRD',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_ADMIN => 'Admin',
        ];

        if (Schema::hasTable('roles')) {
            $dynamicRoles = Role::query()->select(['slug', 'name'])->get();
            foreach ($dynamicRoles as $role) {
                $slug = strtolower(trim((string) $role->slug));
                if ($slug === '') {
                    continue;
                }

                $labels[$slug] = trim((string) $role->name) !== ''
                    ? (string) $role->name
                    : Str::headline($slug);
            }
        }

        return $labels;
    }
}
