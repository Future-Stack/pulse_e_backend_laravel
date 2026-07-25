<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\VettingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD: /admin/vetting — record checks and evidence.
 */
class VettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = VettingRecord::query()
            ->with('provider')
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->filled('check_type'), fn ($q) => $q->where('check_type', $request->string('check_type')))
            ->when($request->boolean('due'), fn ($q) => $q->expired())
            ->orderByDesc('checked_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($records);
    }

    public function store(Request $request, Provider $provider): JsonResponse
    {
        $data = $request->validate([
            'check_type' => ['required', 'in:license,leie,disciplinary,certification,reputation'],
            'status' => ['required', 'in:pass,fail,pending,expired'],
            'evidence_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
            'checked_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'checked_by' => ['nullable', 'string', 'max:80'],
        ]);

        $data['checked_at'] = $data['checked_at'] ?? now();
        $data['checked_by'] = $data['checked_by'] ?? $request->user()?->email ?? 'admin';

        $record = $provider->vettingRecords()->create($data);

        // LEIE hit is a terminal disqualifier — mirrors the leie:screen job (spec 2.3).
        if ($data['check_type'] === 'leie' && $data['status'] === 'fail') {
            $provider->update(['status' => 'excluded']);
        }

        return response()->json(['data' => $record], 201);
    }
}
