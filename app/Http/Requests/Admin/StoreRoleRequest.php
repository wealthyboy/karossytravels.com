<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->isLocal() || $this->user()?->hasPermission('team.manage') === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', 'unique:roles,name'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => 'Use a lowercase slug such as support-agent.'];
    }
}
