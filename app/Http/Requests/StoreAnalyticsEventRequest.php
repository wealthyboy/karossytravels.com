<?php

namespace App\Http\Requests;

use App\Enums\TravelService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAnalyticsEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'service' => ['nullable', Rule::enum(TravelService::class)],
            'funnel_step' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'visitor_id' => ['nullable', 'uuid'],
            'session_id' => ['required', 'uuid'],
            'source' => ['nullable', 'string', 'max:40'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'properties' => ['nullable', 'array'],
            'properties.*' => ['nullable'],
        ];
    }
}
