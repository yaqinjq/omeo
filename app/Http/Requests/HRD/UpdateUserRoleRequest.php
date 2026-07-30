<?php

namespace App\Http\Requests\HRD;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ($user->hasPermission('users_roles.manage') || $user->hasRole(User::ROLE_ADMIN));
    }

    public function rules(): array
    {
        $roleRule = Schema::hasTable('roles')
            ? Rule::exists(Role::class, 'slug')
            : Rule::in(User::ALLOWED_ROLES);

        return [
            'role' => [
                'required',
                'string',
                'max:50',
                $roleRule,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Role user wajib dipilih.',
            'role.exists' => 'Role user tidak valid.',
            'role.in' => 'Role user tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => strtolower(trim((string) $this->input('role'))),
        ]);
    }
}
