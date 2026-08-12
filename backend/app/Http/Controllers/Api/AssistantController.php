<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $lastUserMessage = collect($validated['messages'])
            ->reverse()
            ->firstWhere('role', 'user');
        $question = trim((string) ($lastUserMessage['content'] ?? ''));

        if ($question === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a question.',
            ], 422);
        }

        $relatedTickets = $this->findRelatedResolvedTickets($question);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $this->buildAnswer($question, $relatedTickets),
                'relatedTickets' => $relatedTickets,
                'source' => $relatedTickets->isNotEmpty()
                    ? 'knowledge-base-and-ticket-history'
                    : 'knowledge-base',
            ],
        ]);
    }

    private function findRelatedResolvedTickets(string $question)
    {
        $questionTokens = $this->tokens($question);

        if ($questionTokens === []) {
            return collect();
        }

        return Ticket::query()
            ->with(['category:id,categoryName', 'status:id,statusName'])
            ->whereNotNull('resolutionNote')
            ->where('resolutionNote', '!=', '')
            ->whereHas('status', function ($query): void {
                $query->whereIn('statusName', ['Resolved', 'Closed']);
            })
            ->latest('resolvedAt')
            ->limit(100)
            ->get()
            ->map(function (Ticket $ticket) use ($questionTokens): array {
                $ticketTokens = $this->tokens(
                    $ticket->subject.' '.$ticket->description
                );
                $overlap = array_intersect($questionTokens, $ticketTokens);
                $score = count($overlap) / max(1, count($questionTokens));

                return [
                    'score' => $score,
                    'ticketNumber' => $ticket->ticketNumber,
                    'subject' => $ticket->subject,
                    'category' => $ticket->category?->categoryName,
                    'resolution' => Str::limit($ticket->resolutionNote, 500),
                ];
            })
            ->filter(fn (array $ticket): bool => $ticket['score'] >= 0.2)
            ->sortByDesc('score')
            ->take(3)
            ->values()
            ->map(function (array $ticket): array {
                $ticket['similarity'] = round($ticket['score'] * 100, 1);
                unset($ticket['score']);

                return $ticket;
            });
    }

    private function buildAnswer(string $question, $relatedTickets): string
    {
        $text = Str::lower($question);

        if ($relatedTickets->isNotEmpty()) {
            $best = $relatedTickets->first();

            return "I found a similar resolved issue. Try this previous solution: {$best['resolution']} If it does not solve the problem, create a ticket and include any error message you see.";
        }

        $answers = [
            [['password', 'login', 'sign in', 'account', 'locked'],
                'Check that Caps Lock is off and verify your email or username. Then use Forgot Password. If the account is locked or the reset email does not arrive, create an Account ticket.'],
            [['wifi', 'wi-fi', 'internet', 'network', 'connection'],
                'Turn Wi-Fi off and on, reconnect to the correct network, and restart the device. If other devices also cannot connect, report the affected location in a Network ticket.'],
            [['printer', 'printing', 'paper', 'toner'],
                'Confirm the printer is powered on, has paper, and shows no error. Check that it is the selected default printer, clear stuck print jobs, and try again.'],
            [['email', 'outlook', 'mail'],
                'Check the internet connection, refresh the mailbox, and confirm the password is accepted. Record any send or receive error and create a Software or Account ticket if it continues.'],
            [['slow', 'freeze', 'frozen', 'performance', 'crash'],
                'Save your work, close unused applications, and restart the device. Check available storage and note which application is slow or crashing before creating a ticket.'],
            [['virus', 'malware', 'phishing', 'hacked', 'security'],
                'Disconnect the device from the network and do not open suspicious links or attachments. Create a high-priority Security ticket immediately and contact IT support.'],
            [['create ticket', 'new ticket', 'report issue'],
                'Open Tickets, choose Create Ticket, describe the problem, run Analyze Ticket, review the suggested category and priority, then check for similar tickets before submitting.'],
            [['status', 'my ticket', 'track ticket'],
                'Open Tickets and select the ticket to view its current status, assigned agent, comments, activity timeline, and attachments.'],
        ];

        foreach ($answers as [$keywords, $answer]) {
            foreach ($keywords as $keyword) {
                if (Str::contains($text, $keyword)) {
                    return $answer;
                }
            }
        }

        if (Str::contains($text, ['hello', 'hi', 'hey'])) {
            return 'Hello! Describe your technical problem and include the device, application, location, and any error message. I will suggest the next steps.';
        }

        return 'I could not find a confident solution. Describe the device, application, location, when the issue started, and the exact error message. You can also create a ticket so the support team can investigate.';
    }

    private function tokens(string $text): array
    {
        $stopWords = [
            'a', 'an', 'and', 'are', 'at', 'be', 'but', 'for', 'from',
            'has', 'have', 'i', 'in', 'is', 'it', 'my', 'of', 'on',
            'or', 'the', 'this', 'to', 'was', 'with', 'not', 'can',
        ];

        $words = preg_split(
            '/[^a-z0-9]+/',
            Str::lower(Str::ascii($text)),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return array_values(array_unique(array_filter(
            $words ?: [],
            fn (string $word): bool => strlen($word) >= 3
                && ! in_array($word, $stopWords, true)
        )));
    }
}