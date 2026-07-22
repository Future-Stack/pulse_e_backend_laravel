<?php

namespace App\Jobs;

use App\Models\LabReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessLabReportAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 3;

    public $timeout = 600;


    public function __construct(
        public LabReport $labReport
    ) {}



    public function handle(): void
    {

        try {

            $this->labReport->update([
                'analysis_status'=>'processing'
            ]);


            $url = config('services.ai.base_url')
                .'/api/summarize-pdf';



            $response = Http::retry(3,2000)
                ->withoutVerifying()
                ->acceptJson()
                ->timeout(600)
                ->post($url,[
                    'report_id'=>$this->labReport->id,

                    'source_path'=>asset(
                        'storage/'.$this->labReport->lab_report
                    )
                ]);



            if(! $response->successful()){

                throw new \Exception(
                    'AI service failed. Status: '
                    .$response->status()
                    .' Body: '
                    .$response->body()
                );
            }



            $result=$response->json();


            $summary=$result['summary'] ?? [];



            $this->labReport->update([

                'panel'=>$summary['panel'] ?? null,

                'biomarkers'=>$summary['biomarkers'] ?? null,

                'ai_insights'=>$summary['ai_insights'] ?? null,

                'next_steps'=>$summary['next_steps'] ?? null,

                'analysis_status'=>'completed',

            ]);



        }catch(\Throwable $e){


            Log::error('Lab Report AI Error',[

                'report_id'=>$this->labReport->id,

                'message'=>$e->getMessage(),

            ]);


            $this->labReport->update([
                'analysis_status'=>'failed'
            ]);


            throw $e;
        }
    }
}