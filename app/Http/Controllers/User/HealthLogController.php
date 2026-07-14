<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\HealthLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HealthLogController extends Controller
{
    /**
     * Display all health logs of authenticated user.
     */
    public function index()
    {
        try {
            $healthLogs = HealthLog::where('user_id', Auth::id())
                ->latest('log_date')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Health logs retrieved successfully.',
                'data' => $healthLogs,
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve health logs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created health log.
     */
    public function store(Request $request)
    {
        
        $validated = $request->validate([
            'log_date' => 'required|date',
            'mood' => 'required|string|max:10',
            'energy_level' => [
                'required',
                Rule::in([
                    'Very Low',
                    'Low',
                    'Moderate',
                    'High',
                    'Very High',
                ]),
            ],
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {

            $healthLog = HealthLog::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'log_date' => $validated['log_date'],
                ],
                [
                    'mood' => $validated['mood'],
                    'energy_level' => $validated['energy_level'],
                    'symptoms' => $validated['symptoms'] ?? [],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Health log saved successfully.',
                'data' => $healthLog,
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to save health log.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified health log.
     */
    public function show($id)
    {
        $healthLog = HealthLog::where('user_id', Auth::id())
            ->find($id);

        if (!$healthLog) {
            return response()->json([
                'success' => false,
                'message' => 'Health log not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $healthLog,
        ]);
    }

    /**
     * Update the specified health log.
     */
    public function update(Request $request, $id)
    {
        $healthLog = HealthLog::where('user_id', Auth::id())
            ->find($id);

        if (!$healthLog) {
            return response()->json([
                'success' => false,
                'message' => 'Health log not found.',
            ], 404);
        }

        $validated = $request->validate([
            'log_date' => 'sometimes|date',
            'mood' => 'sometimes|string|max:10',
            'energy_level' => [
                'sometimes',
                Rule::in([
                    'Very Low',
                    'Low',
                    'Moderate',
                    'High',
                    'Very High',
                ]),
            ],
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $healthLog->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Health log updated successfully.',
            'data' => $healthLog->fresh(),
        ]);
    }

    /**
     * Remove the specified health log.
     */
    public function destroy($id)
    {
        $healthLog = HealthLog::where('user_id', Auth::id())
            ->find($id);

        if (!$healthLog) {
            return response()->json([
                'success' => false,
                'message' => 'Health log not found.',
            ], 404);
        }

        $healthLog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Health log deleted successfully.',
        ]);
    }



        public function today()
    {
        $healthLog = HealthLog::where('user_id', auth()->id())
            ->whereDate('log_date', today())
            ->first();

        return response()->json([
            'success' => true,
            'data' => $healthLog,
        ]);
    }
}