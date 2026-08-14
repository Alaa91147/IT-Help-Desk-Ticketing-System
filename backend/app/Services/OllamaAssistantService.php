<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaAssistantService
{
    public function chat(
        array $messages,
        array $relatedTickets = [],
        array $ticketContext = []
    ): string {
        $ollamaMessages = [
            [
                'role' => 'system',
                'content' =>
                    $this->systemPrompt(),
            ],
        ];

        if ($relatedTickets !== []) {
            $ollamaMessages[] = [
                'role' => 'system',
                'content' =>
                    'Similar resolved tickets from the '
                    ."help-desk knowledge base:\n".
                    json_encode(
                        $relatedTickets,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    ),
            ];
        }

        if ($ticketContext !== []) {
            $ollamaMessages[] = [
                'role' => 'system',
                'content' =>
                    'Live ticket information for the '
                    ."logged-in staff member:\n".
                    json_encode(
                        $ticketContext,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    ),
            ];
        }

        foreach ($messages as $message) {
            $ollamaMessages[] = [
                'role' =>
                    $message['role'],

                'content' =>
                    $message['content'],
            ];
        }

        $response = Http::connectTimeout(5)
            ->timeout(180)
            ->post(
                config(
                    'services.ollama.url',
                    'http://127.0.0.1:11434'
                ).'/api/chat',
                [
                    'model' => config(
                        'services.ollama.model',
                        'llama3.2:3b'
                    ),

                    'messages' =>
                        $ollamaMessages,

                    'stream' => false,

                    'options' => [
                        'temperature' => 0.2,
                        'num_gpu' => 0,
                    ],
                ]
            );

        $response->throw();

        $answer = trim(
            (string) $response->json(
                'message.content',
                ''
            )
        );

        if ($answer === '') {
            throw new RuntimeException(
                'Ollama returned an empty response.'
            );
        }

        return $answer;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the AI support assistant for an IT help-desk ticketing system.

Give clear, short, practical troubleshooting instructions.

Rules:
- Use simple language.
- Return plain text only.
- Do not use Markdown symbols such as **, #, or backticks.
- Ask for missing information when necessary.
- Never invent ticket information.
- Never invent ticket counts, numbers, statuses, priorities, assignments, or due dates.
- Answer ticket questions using the provided live ticket information.
- The live information is already filtered according to the logged-in staff member's permissions.
- Use similar resolved tickets only when relevant.
- Never request passwords, OTP codes, API keys, or other secrets.
- For malware, phishing, data breaches, or hacked accounts, recommend immediate escalation to IT security.
- Do not claim that an action was performed.
- If a problem cannot be solved safely, recommend creating or escalating a support ticket.
PROMPT;
    }
}