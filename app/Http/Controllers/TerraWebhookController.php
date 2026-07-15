<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TerraWebhookController extends Controller
{
    public function generateWidgetSession(Request $request)
    {
        $response = Http::withHeaders([
            'dev-id' => config('services.terra.dev_id'),
            'x-api-key' => config('services.terra.api_key'),
        ])->post(config('services.terra.base_url').'/auth/generateWidgetSession', [
            'reference_id' => (string) $request->user()->id,
            'providers' => 'OURA,FITBIT,GARMIN',
            'language' => 'en',
            'auth_success_redirect_url' => 'https://yourapp.com/terra/success',
            'auth_failure_redirect_url' => 'https://yourapp.com/terra/failure',
        ]);

        return $response->json();
    }

    public function handle(Request $request)
    {
        Log::info('Terra webhook hit', $request->all());

        $signature = $request->header('terra-signature');

        // if (!$this->verifySignature($request->getContent(), $signature)) {
        //     Log::warning('Terra signature verification failed');
        //     return response()->json(['error' => 'invalid signature'], 401);
        // }

        $payload = $request->all();
        $type = $payload['type'] ?? null;
        $terraUserId = $payload['user']['user_id'] ?? null;
        $referenceId = $payload['user']['reference_id'] ?? null;

        $user = $referenceId ? \App\Models\User::find($referenceId) : null;

        try {
            if ($type === 'auth') {
                \App\Models\TerraConnection::updateOrCreate(
                    ['terra_user_id' => $terraUserId],
                    [
                        'user_id' => $user?->id,
                        'reference_id' => $referenceId,
                        'provider' => $payload['user']['provider'] ?? null,
                        'active' => true,
                    ]
                );
            } else {
                \App\Models\TerraActivityData::create([
                    'user_id' => $user?->id,
                    'terra_user_id' => $terraUserId,
                    'type' => $type,
                    'payload' => $payload,
                    'data_generated_at' => $payload['data'][0]['metadata']['start_time'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Terra webhook save failed: '.$e->getMessage());
        }

        return response()->json(['status' => 'received']);
    }

    private function verifySignature($body, $signature)
    {
        if (!$signature) {
            return false;
        }

        $secret = config('services.terra.signing_secret');
        [$t, $sig] = $this->parseSignatureHeader($signature);
        $expected = hash_hmac('sha256', $t.'.'.$body, $secret);
        return hash_equals($expected, $sig);
    }

    private function parseSignatureHeader($signature)
    {
        $parts = explode(',', $signature);
        $t = null;
        $sig = null;

        foreach ($parts as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') {
                $t = $value;
            } elseif ($key === 'v1') {
                $sig = $value;
            }
        }

        return [$t, $sig];
    }

    public function getActivityData(Request $request)
    {
        $query = \App\Models\TerraActivityData::where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $data = $query->latest()->paginate(20);

        return response()->json($data);
    }

    public function getConnections(Request $request)
    {
        $connections = \App\Models\TerraConnection::where('user_id', $request->user()->id)
            ->get();

        return response()->json($connections);
    }

}