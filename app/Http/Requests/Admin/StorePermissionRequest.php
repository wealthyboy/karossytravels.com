<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StorePermissionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/', 'unique:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => 'Use resource.action format, for example reports.export.'];
    }
}
