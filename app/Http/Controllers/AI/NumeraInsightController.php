<?php
namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\NumeraInsight;
use App\Jobs\FetchNumeraInsightJob;
use Illuminate\Http\JsonResponse;


class NumeraInsightController extends Controller
{

    public function show(int $userId): JsonResponse
    {

        $user = User::find($userId);


        if(!$user){

            return response()->json([
                'success'=>false,
                'message'=>'User not found'
            ],404);

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