<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateHotelOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['operator_markup_type' => $this->input('operator_markup_type', 'none')]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'addons' => ['nullable', 'array', 'max:20'],
            'addons.*' => ['uuid', 'distinct', Rule::exists('addons', 'id')->where(fn ($query) => $query->where('type', 'hotel')->where('active', true))],
            'operator_markup_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'operator_markup_value' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'required_unless:operator_markup_type,none'],
        ];
    }
}
