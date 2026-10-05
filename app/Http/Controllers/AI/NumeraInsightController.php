<?php
namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\NumeraInsight;
use App\Jobs\FetchNumeraInsightJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class NumeraInsightController extends Controller
{

    public function show(Request $request, int $userId): JsonResponse
    {

        $user = User::find($userId);


        if(!$user){

            return response()->json([
                'success'=>false,
                'message'=>'User not found'
            ],404);

        }


        // Force refresh: call AI synchronously and return fresh data
        if ($request->boolean('refresh')) {

            try {

                $url = config('services.ai.base_url') . '/api/numera-insight';

                $response = Http::retry(3, 2000)
                    ->withoutVerifying()
                    ->acceptJson()
                    ->timeout(300)
                    ->get($url, ['user_id' => $userId]);

                if (!$response->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'AI service error: ' . $response->status(),
                    ], 502);
                }

                $data = $response->json();

                if (!isset($data['numera_insight'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid AI response.',
                    ], 502);
                }

                $result = $data['numera_insight'];

                // Invalidate old records
                NumeraInsight::where('user_id', $userId)
                    ->whereDate('created_at', today())
                    ->whereIn('status', ['completed', 'failed'])
                    ->update(['status' => 'failed']);

                // Save fresh insight directly as completed
                $insight = NumeraInsight::create([
                    'user_id'     => $userId,
                    'title'       => $result['title'] ?? null,
                    'tag'         => $result['tag'] ?? null,
                    'eyebrow'     => $result['eyebrow'] ?? null,
                    'headline'    => $result['headline'] ?? null,
                    'description' => $result['description'] ?? null,
                    'cycle_day'   => $result['cycle_day'] ?? null,
                    'theme'       => $result['theme'] ?? null,
                    'priority'    => $result['priority'] ?? null,
                    'status'      => 'completed',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Numera insight refreshed successfully.',
                    'data'    => $insight,
                ]);

            } catch (\Throwable $e) {

                Log::error('Numera Insight refresh failed.', [
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh insight.',
                ], 500);
            }
        }


        $insight = NumeraInsight::where('user_id',$userId)
            ->whereDate('created_at',today())
            ->latest()
            ->first();



        // completed
        if($insight && $insight->status === 'completed'){


            return response()->json([
                'success'=>true,
                'message'=>'Numera insight fetched successfully.',
                'data'=>$insight
            ]);

        }



        // running
        if($insight && in_array($insight->status,[
            'pending',
            'processing'
        ])){


            return response()->json([
                'success'=>true,
                'message'=>'Numera insight generating.',
                'data'=>[
                    'id'=>$insight->id,
                    'status'=>$insight->status
                ]
            ],202);


        }




        // failed retry
        if($insight && $insight->status==='failed'){


            $insight->update([
                'status'=>'pending'
            ]);


            FetchNumeraInsightJob::dispatch($insight->id);



            return response()->json([
                'success'=>true,
                'message'=>'Numera insight retry started.',
                'status'=>'pending'
            ],202);

        }




        // new generate

        $insight = NumeraInsight::create([

            'user_id'=>$userId,

            'status'=>'pending'

        ]);



        FetchNumeraInsightJob::dispatch($insight->id);



        return response()->json([

            'success'=>true,

            'message'=>'Numera insight generation started.',

            'data'=>[
                'id'=>$insight->id,
                'status'=>'pending'
            ]

        ],202);


    }

}