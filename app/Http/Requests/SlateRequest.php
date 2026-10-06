<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enforces the privacy seam described in spec 2.1: inbound requests carry exactly
 * three parameters (category, zip/metro_id, optional life_stage) plus paging.
 * No user ID, insight ID, or health signal is ever accepted here.
 */
class SlateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasAuthJourney = false;
        $user = $this->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        if ($user && $user->profile && $user->profile->lifeJourneys()->exists()) {
            $hasAuthJourney = true;
        }

        $categoryRule = $hasAuthJourney
            ? ['nullable', 'string', 'exists:provider_categories,slug']
            : ['required_without_all:life_journey_id,journey_id,life_stage', 'nullable', 'string', 'exists:provider_categories,slug'];

        return [
            'category' => $categoryRule,
            'life_journey_id' => ['nullable'],
            'journey_id' => ['nullable'],
            'life_stage' => ['nullable', 'string'],
            'zip' => ['required_without_all:metro_id,lat', 'nullable', 'digits:5'],
            'metro_id' => ['required_without_all:zip,lat', 'nullable', 'integer', 'exists:metros,id'],
            'lat' => ['required_without_all:zip,metro_id', 'required_with:lng', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_with:lat', 'nullable', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
