<?php

namespace App\Http\Requests;

use App\Models\SlateEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Batched client events. Deny-by-default: 'prohibited' rules on each event item
 * reject any extra fields, which is the enforcement point that keeps user IDs,
 * insight IDs, and other health signals from ever landing in slate_events.
 */
class StoreSlateEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'size:32'],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            // 'array:...' restricts each event item to exactly these keys — this is the
            // deny-by-default guard: any extra field on an event item fails validation.
            'events.*' => ['array:event_type,category,metro_id,provider_id,slot_position,sponsored,occurred_at'],
            'events.*.event_type' => ['required', 'string', 'in:' . implode(',', SlateEvent::ALLOWED_EVENT_TYPES)],
            'events.*.category' => ['required', 'string', 'exists:provider_categories,slug'],
            'events.*.metro_id' => ['nullable', 'integer', 'exists:metros,id'],
            'events.*.provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'events.*.slot_position' => ['nullable', 'integer', 'min:1', 'max:5'],
            'events.*.sponsored' => ['nullable', 'boolean'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ];
    }
}
