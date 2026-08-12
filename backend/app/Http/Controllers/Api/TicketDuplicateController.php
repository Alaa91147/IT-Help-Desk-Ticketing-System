<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TicketDuplicateDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketDuplicateController extends Controller
{
    public function check(
        Request $request,
        TicketDuplicateDetector $detector
    ): JsonResponse {
        $validated = $request->validate([
            'subject' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
                'min:5',
                'max:10000',
            ],
        ]);

        $result = $detector->check(
            $validated['subject'],
            $validated['description']
        );

        return response()->json([
            'success' => true,
            'message' => $result[
                'hasPotentialDuplicate'
            ]
                ? 'Possible related tickets were found.'
                : 'No similar tickets were found.',
            'data' => $result,
        ]);
    }
}