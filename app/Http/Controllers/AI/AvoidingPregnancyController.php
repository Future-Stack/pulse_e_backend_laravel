<?php


namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AvoidingPregnancyController extends Controller
{
    //
    public function setMode(Request $request)
{

    $request->validate([
        'mode'=>'required|string'
    ]);


    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/mode';


    $response = Http::timeout(120)
        ->acceptJson()
        ->post($url,[
            'mode'=>$request->mode
        ]);


    if(!$response->successful()){

        return response()->json([
            'success'=>false,
            'message'=>'Unable to set cycle mode'
        ],500);

    }


    return response()->json([
        'success'=>true,
        'data'=>$response->json()
    ]);

}


public function consent(Request $request)
{

    $request->validate([
        'consented'=>'required|boolean',
        'consent_version'=>'required|string'
    ]);


    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/avoiding-pregnancy/consent';


    $response = Http::timeout(120)
        ->acceptJson()
        ->post($url,[
            'consented'=>$request->consented,
            'consent_version'=>$request->consent_version
        ]);


    if(!$response->successful()){

        return response()->json([
            'success'=>false,
            'message'=>'Unable to save consent'
        ],500);

    }


    return response()->json([
        'success'=>true,
        'data'=>$response->json()
    ]);

}


public function consentStatus()
{
    $url = config('services.ai.base_url')
        . '/api/v1/cycle-engine/avoiding-pregnancy/consent-status';


    $response = Http::timeout(120)
        ->acceptJson()
        ->get($url);


    if(!$response->successful()){

        return response()->json([
            'success'=>false,
            'message'=>'Unable to fetch consent status'
        ],500);

    }


    return response()->json([
        'success'=>true,
        'data'=>$response->json()
    ]);
}
}
