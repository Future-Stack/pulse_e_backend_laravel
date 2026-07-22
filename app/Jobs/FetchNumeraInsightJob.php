<?php

namespace App\Jobs;


use App\Models\NumeraInsight;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FetchNumeraInsightJob implements ShouldQueue
{

    use Queueable;


    public int $tries = 3;

    public int $timeout = 600;


    protected int $insightId;



    public function __construct(int $insightId)
    {
        $this->insightId = $insightId;
    }




    public function handle():void
    {

        $insight = NumeraInsight::find($this->insightId);



        if(!$insight){

            Log::error('Numera insight not found');

            return;

        }



        try{


            // pending -> processing

            $insight->update([
                'status'=>'processing'
            ]);



            $url = config('services.ai.base_url')
                .'/api/numera-insight';



            $response = Http::retry(3,2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(300)
                ->get($url);



            if(!$response->successful()){


                throw new \Exception(
                    "AI Error ".$response->body()
                );

            }




            $data=$response->json();



            if(!isset($data['numera_insight'])){

                throw new \Exception(
                    'numera_insight missing'
                );

            }



            $result=$data['numera_insight'];



            $insight->update([


                'title'=>$result['title'] ?? null,

                'tag'=>$result['tag'] ?? null,

                'eyebrow'=>$result['eyebrow'] ?? null,

                'headline'=>$result['headline'] ?? null,

                'description'=>$result['description'] ?? null,

                'cycle_day'=>$result['cycle_day'] ?? null,

                'theme'=>$result['theme'] ?? null,

                'priority'=>$result['priority'] ?? null,


                'status'=>'completed'

            ]);




        }catch(\Throwable $e){


            Log::error('Numera Insight Failed',[

                'id'=>$this->insightId,

                'message'=>$e->getMessage()

            ]);



            $insight->update([
                'status'=>'failed'
            ]);


            throw $e;

        }


    }

}