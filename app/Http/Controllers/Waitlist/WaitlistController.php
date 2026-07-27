<?php

namespace App\Http\Controllers\Waitlist;

use App\Http\Controllers\Controller;
use App\Mail\WaitlistConfirmation;
use App\Mail\WaitlistInvite;
use App\Mail\WaitlistWelcome;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitlistController extends Controller
{
//1. User joins the waitlist from the landing page.
//2. A pending_confirmation waitlist record is created and a confirmation e-m-a-i-l is sent.
//3. After confirming the e-m-a-i-l, the status changes to confirmed and the welcome e-m-a-i-l is sent.
//4. When a beta wave is released, selected users receive a beta invite and their status changes to invited.
//5. The user installs the beta app from the invitation.
//6. The user then registers or signs in through the existing application authentication flow.
//7. Once the account is successfully created, we'll match the e-m-a-i-l address with the corresponding waitlist entry and update its status to activated.
    public function submit(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:waitlist_entries,email',
                'life_journey_id' => 'nullable|exists:life_journeys,id',
            ]);

            $exist = WaitlistEntry::where('email', $request->email)->first();
            if ($exist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists',
                ]);
            }

            $entry = WaitlistEntry::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'life_journey_id' => $validated['life_journey_id'] ?? null,
                'status' => 'pending_confirmation',
                'confirmation_token' => Str::random(32),
            ]);

            $confirmationUrl = asset('/api/v1/waitlist/confirmation/' . $entry->confirmation_token);

            Mail::to($entry->email)->queue(
                new WaitlistConfirmation($entry, $confirmationUrl)
            );

            return response()->json([
                'success' => true,
                'message' => 'Waitlist entry created successfully.',
                'data' => $entry,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Waitlist entry failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmation(string $token)
    {
        try {

            $exist = WaitlistEntry::where('confirmation_token', $token)->first();

            if ($exist) {
                $exist->update([
                    'confirmed_at' => now(),
                    'status' => 'confirmed',
                ]);

                Mail::to($exist->email)->queue(
                    new WaitlistWelcome()
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Waitlist entry successfully confirmed.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Waitlist entry not Found.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Waitlist entry failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getWaitlist()
    {
        try {
            $list = WaitlistEntry::where('status', 'confirmed')->get();

            return response()->json([
                'success' => true,
                'data' => $list,
            ]);
        } catch (\Exception $e) {
            \Log::error('Waitlist entry failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function sendSingleInvite(Request $request)
    {
        try {
            $validated = $request->validate([
                'waitlist_entry_id' => 'required|exists:waitlist_entries,id',
                'link' => 'required|url',
            ]);

            $entry = WaitlistEntry::findOrFail($validated['waitlist_entry_id']);

            $entry->update([
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            Mail::to($entry->email)->queue(
                new WaitlistInvite($validated['link'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Invite sent successfully.',
                'data' => $entry,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Single invite failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

//    public function sendBulkInvite(Request $request)
//    {
//        try {
//            $validated = $request->validate([
//                'waitlist_entry_id' => 'required|array',
//                'waitlist_entry_id.*' => 'exists:waitlist_entries,id',
//                'link' => 'required|url',
//            ]);
//
//            $entries = WaitlistEntry::whereIn('id', $validated['waitlist_entry_id'])->get();
//
//            foreach ($entries as $entry) {
//                Mail::to($entry->email)->queue(
//                    new WaitlistInvite($entry, $validated['link'])
//                );
//            }
//
//            return response()->json([
//                'success' => true,
//                'message' => 'Bulk invites sent successfully.',
//                'total' => $entries->count(),
//            ], 200);
//
//        } catch (\Exception $e) {
//            \Log::error('Bulk invite failed: ' . $e->getMessage());
//            return response()->json([
//                'success' => false,
//                'message' => $e->getMessage(),
//            ], 500);
//        }
//    }
}
