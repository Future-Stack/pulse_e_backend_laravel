<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLabReportAI;
use App\Models\LabReport;
use Illuminate\Http\JsonResponse;

class LabReportAIController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $labReport = LabReport::find($id);


        if (! $labReport) {
            return response()->json([
                'success' => false,
                'message' => 'Lab report not found.'
            ], 404);
        }


        /**
         * Already completed
         */
        if (
            $labReport->analysis_status === 'completed'
            && !empty($labReport->panel)
        ) {

            $labReport->lab_report_url =
                asset('storage/'.$labReport->lab_report);


            return response()->json([
                'success' => true,
                'message' => 'AI report loaded successfully.',
                'data' => $labReport
            ]);
        }



        /**
         * Already running
         */
        if (in_array($labReport->analysis_status, [
            'pending',
            'processing'
        ])) {

            return response()->json([
                'success' => true,
                'message' => 'AI analysis is being generated.',
                'data' => [
                    'id' => $labReport->id,
                    'status' => $labReport->analysis_status
                ]
            ],202);
        }



        /**
         * Failed retry
         */
        if ($labReport->analysis_status === 'failed') {

            $labReport->update([
                'analysis_status'=>'pending'
            ]);


            ProcessLabReportAI::dispatch($labReport);


            return response()->json([
                'success'=>true,
                'message'=>'AI analysis retry started.',
                'data'=>[
                    'id'=>$labReport->id,
                    'status'=>'pending'
                ]
            ],202);
        }



        /**
         * First time generate
         */
        $labReport->update([
            'analysis_status'=>'pending'
        ]);


        ProcessLabReportAI::dispatch($labReport);



        return response()->json([
            'success'=>true,
            'message'=>'AI analysis started.',
            'data'=>[
                'id'=>$labReport->id,
                'status'=>'pending'
            ]
        ],202);
    }
}