<?php

namespace App\Http\Controllers;

use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabReportController extends Controller
{
    /**
     * Display all lab reports.
     */
    public function index()
    {
        $labReports = LabReport::with('user')->latest()->get();

        $labReports->transform(function ($labReport) {
            $labReport->lab_report = $labReport->lab_report
                ? asset('storage/' . $labReport->lab_report)
                : null;

            return $labReport;
        });

        return response()->json([
            'success' => true,
            'data' => $labReports,
        ]);
    }

    /**
     * Store a newly created lab report.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lab_report' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = null;

        if ($request->hasFile('lab_report')) {
            $path = $request->file('lab_report')->store('lab_reports', 'public');
        }

        $labReport = LabReport::create([
            'user_id'    => auth()->id(),
            'lab_report' => $path,
        ]);

        $labReport->lab_report = $labReport->lab_report
            ? asset('storage/' . $labReport->lab_report)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Lab report created successfully.',
            'data' => $labReport,
        ], 201);
    }

    /**
     * Display the specified lab report.
     */
    public function show(LabReport $labReport)
    {
        $labReport->load('user');

        $labReport->lab_report = $labReport->lab_report
            ? asset('storage/' . $labReport->lab_report)
            : null;

        return response()->json([
            'success' => true,
            'data' => $labReport,
        ]);
    }

    /**
     * Update the specified lab report.
     */
    public function update(Request $request, LabReport $labReport)
    {
        $request->validate([
            'lab_report' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('lab_report')) {

            if ($labReport->lab_report && Storage::disk('public')->exists($labReport->lab_report)) {
                Storage::disk('public')->delete($labReport->lab_report);
            }

            $labReport->lab_report = $request->file('lab_report')->store('lab_reports', 'public');
            $labReport->save();
        }

        $labReport->lab_report = $labReport->lab_report
            ? asset('storage/' . $labReport->lab_report)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Lab report updated successfully.',
            'data' => $labReport,
        ]);
    }

    /**
     * Remove the specified lab report.
     */
    public function destroy(LabReport $labReport)
    {
        if ($labReport->lab_report && Storage::disk('public')->exists($labReport->lab_report)) {
            Storage::disk('public')->delete($labReport->lab_report);
        }

        $labReport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lab report deleted successfully.',
        ]);
    }
}