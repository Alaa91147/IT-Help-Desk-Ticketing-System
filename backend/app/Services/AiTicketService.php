<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Priority;
use Illuminate\Support\Collection;

class AiTicketService
{
    /**
     * Weighted indicators for category detection.
     *
     * Change the array keys so they correspond with the
     * category names stored in your database.
     */
    private const CATEGORY_INDICATORS = [
        'Network' => [
            'wifi' => 5,
            'wi fi' => 5,
            'internet' => 5,
            'network' => 4,
            'connection' => 3,
            'connectivity' => 4,
            'router' => 4,
            'ethernet' => 4,
            'lan' => 4,
            'vpn' => 5,
            'dns' => 5,
            'ip address' => 4,
            'slow internet' => 6,
            'no internet' => 7,
            'cannot connect' => 3,
        ],

        'Hardware' => [
            'computer' => 2,
            'laptop' => 3,
            'desktop' => 3,
            'monitor' => 5,
            'screen' => 4,
            'keyboard' => 5,
            'mouse' => 5,
            'printer' => 6,
            'scanner' => 6,
            'hard drive' => 6,
            'ssd' => 5,
            'ram' => 5,
            'battery' => 5,
            'charger' => 5,
            'overheating' => 6,
            'broken' => 3,
            'not turning on' => 7,
            'blue screen' => 7,
        ],

        'Software' => [
            'software' => 4,
            'application' => 3,
            'app' => 2,
            'program' => 3,
            'install' => 4,
            'installation' => 4,
            'update' => 3,
            'crash' => 6,
            'crashing' => 6,
            'error message' => 5,
            'not responding' => 6,
            'freeze' => 5,
            'frozen' => 5,
            'license' => 4,
            'windows' => 3,
            'office' => 3,
        ],

        'Account' => [
            'account' => 4,
            'login' => 5,
            'log in' => 5,
            'sign in' => 5,
            'password' => 6,
            'reset password' => 7,
            'forgot password' => 7,
            'username' => 5,
            'locked out' => 7,
            'access denied' => 6,
            'authentication' => 6,
            'verification code' => 5,
            'otp' => 5,
            'permission' => 4,
        ],

        'Email' => [
            'email' => 5,
            'e mail' => 5,
            'outlook' => 5,
            'mailbox' => 5,
            'inbox' => 4,
            'spam' => 4,
            'attachment' => 3,
            'cannot send' => 6,
            'cannot receive' => 6,
            'mail delivery' => 6,
        ],

        'Security' => [
            'security' => 5,
            'virus' => 7,
            'malware' => 7,
            'ransomware' => 8,
            'phishing' => 8,
            'hacked' => 8,
            'breach' => 8,
            'unauthorized access' => 8,
            'suspicious login' => 7,
            'stolen password' => 8,
            'data leak' => 8,
        ],
    ];

    private const URGENT_INDICATORS = [
        'security breach' => 12,
        'ransomware' => 12,
        'hacked' => 10,
        'data leak' => 10,
        'system down' => 10,
        'server down' => 10,
        'entire company' => 9,
        'all users' => 8,
        'business stopped' => 10,
        'production down' => 12,
        'cannot work' => 7,
        'critical' => 8,
        'emergency' => 8,
    ];

    private const HIGH_INDICATORS = [
        'unable to work' => 7,
        'cannot work' => 7,
        'multiple users' => 6,
        'many users' => 6,
        'department' => 5,
        'no internet' => 6,
        'printer not working' => 4,
        'application crashing' => 5,
        'account locked' => 5,
        'deadline' => 4,
        'urgent' => 5,
    ];

    private const LOW_INDICATORS = [
        'question' => 3,
        'how do i' => 4,
        'when possible' => 4,
        'minor' => 4,
        'suggestion' => 5,
        'request information' => 4,
        'cosmetic' => 5,
        'not urgent' => 7,
    ];

    public function categorizeAndPrioritize(
        string $subject,
        string $description
    ): array {
        $categories = Category::query()
            ->where('isActive', true)
            ->get();

        $priorities = Priority::query()
            ->where('isActive', true)
            ->get();

        if (
            $categories->isEmpty() ||
            $priorities->isEmpty()
        ) {
            return $this->failure(
                'No active categories or priorities are configured.'
            );
        }

        $subjectText = $this->normalize($subject);
        $descriptionText = $this->normalize($description);

        $combinedText =
            $subjectText.' '.$descriptionText;

        $categoryResult = $this->detectCategory(
            $subjectText,
            $descriptionText,
            $categories
        );

        $priorityResult = $this->detectPriority(
            $combinedText,
            $priorities
        );

        return [
            'success' => true,

            'category' => [
                'id' => $categoryResult['category']->id,
                'name' =>
                    $categoryResult['category']->categoryName,
            ],

            'priority' => [
                'id' => $priorityResult['priority']->id,
                'name' =>
                    $priorityResult['priority']->priorityName,
            ],

            'confidence' => round(
                (
                    $categoryResult['confidence'] +
                    $priorityResult['confidence']
                ) / 2,
                2
            ),

            'categoryConfidence' =>
                $categoryResult['confidence'],

            'priorityConfidence' =>
                $priorityResult['confidence'],

            'reason' =>
                $categoryResult['reason'].' '.
                $priorityResult['reason'],

            'matchedIndicators' => [
                'category' =>
                    $categoryResult['matches'],

                'priority' =>
                    $priorityResult['matches'],
            ],

            'metadata' => [
                'engine' =>
                    'weighted_nlp_classifier',

                'generatedAt' =>
                    now()->toISOString(),
            ],
        ];
    }

    private function detectCategory(
        string $subject,
        string $description,
        Collection $categories
    ): array {
        $scores = [];
        $matches = [];

        foreach ($categories as $category) {
            $categoryName = $category->categoryName;

            $indicators =
                $this->indicatorsForCategory(
                    $categoryName
                );

            $scores[$category->id] = 0;
            $matches[$category->id] = [];

            foreach ($indicators as $phrase => $weight) {
                $subjectMatches =
                    substr_count($subject, $phrase);

                $descriptionMatches =
                    substr_count(
                        $description,
                        $phrase
                    );

                if (
                    $subjectMatches === 0 &&
                    $descriptionMatches === 0
                ) {
                    continue;
                }

                // Subject matches have twice the weight.
                $score =
                    ($subjectMatches * $weight * 2) +
                    ($descriptionMatches * $weight);

                $scores[$category->id] += $score;

                $matches[$category->id][] = $phrase;
            }
        }

        arsort($scores);

        $selectedId = array_key_first($scores);
        $bestScore = $scores[$selectedId] ?? 0;
        $totalScore = array_sum($scores);

        $selectedCategory = $categories
            ->firstWhere('id', $selectedId);

        if ($bestScore === 0) {
            $selectedCategory =
                $this->fallbackCategory($categories);

            return [
                'category' => $selectedCategory,
                'confidence' => 0.3,
                'matches' => [],
                'reason' =>
                    "No strong category indicator was found; "
                    ."{$selectedCategory->categoryName} was used "
                    .'as the configured fallback.',
            ];
        }

        $confidence = min(
            0.98,
            0.5 +
            ($bestScore / max(1, $totalScore)) * 0.48
        );

        return [
            'category' => $selectedCategory,
            'confidence' => round(
                $confidence,
                2
            ),
            'matches' => array_values(
                array_unique(
                    $matches[$selectedId]
                )
            ),
            'reason' =>
                "{$selectedCategory->categoryName} was selected "
                .'from the detected issue indicators.',
        ];
    }

    private function detectPriority(
        string $text,
        Collection $priorities
    ): array {
        $urgent = $this->scoreIndicators(
            $text,
            self::URGENT_INDICATORS
        );

        $high = $this->scoreIndicators(
            $text,
            self::HIGH_INDICATORS
        );

        $low = $this->scoreIndicators(
            $text,
            self::LOW_INDICATORS
        );

        if ($urgent['score'] > 0) {
            $desiredName = 'Urgent';
            $result = $urgent;
        } elseif ($high['score'] > 0) {
            $desiredName = 'High';
            $result = $high;
        } elseif ($low['score'] > 0) {
            $desiredName = 'Low';
            $result = $low;
        } else {
            $desiredName = 'Medium';
            $result = [
                'score' => 0,
                'matches' => [],
            ];
        }

        $priority = $this->findPriority(
            $priorities,
            $desiredName
        );

        $confidence = $result['score'] > 0
            ? min(
                0.98,
                0.55 + $result['score'] / 40
            )
            : 0.55;

        return [
            'priority' => $priority,
            'confidence' => round(
                $confidence,
                2
            ),
            'matches' => $result['matches'],
            'reason' =>
                "{$priority->priorityName} priority was selected "
                .'based on the reported impact and urgency.',
        ];
    }

    private function indicatorsForCategory(
        string $categoryName
    ): array {
        foreach (
            self::CATEGORY_INDICATORS
            as $configuredName => $indicators
        ) {
            if (
                str_contains(
                    $this->normalize($categoryName),
                    $this->normalize($configuredName)
                )
            ) {
                return $indicators;
            }
        }

        return [];
    }

    private function scoreIndicators(
        string $text,
        array $indicators
    ): array {
        $score = 0;
        $matches = [];

        foreach ($indicators as $phrase => $weight) {
            $count = substr_count(
                $text,
                $phrase
            );

            if ($count === 0) {
                continue;
            }

            $score += $count * $weight;
            $matches[] = $phrase;
        }

        return [
            'score' => $score,
            'matches' => array_values(
                array_unique($matches)
            ),
        ];
    }

    private function fallbackCategory(
        Collection $categories
    ): Category {
        return $categories->first(
            fn (Category $category): bool =>
                in_array(
                    $this->normalize(
                        $category->categoryName
                    ),
                    [
                        'other',
                        'general',
                        'general support',
                    ],
                    true
                )
        ) ?? $categories->first();
    }

    private function findPriority(
        Collection $priorities,
        string $desiredName
    ): Priority {
        return $priorities->first(
            fn (Priority $priority): bool =>
                $this->normalize(
                    $priority->priorityName
                ) ===
                $this->normalize($desiredName)
        ) ??
            $priorities->sortBy('priorityLevel')->first();
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(
            trim($text)
        );

        $text = preg_replace(
            '/[^\pL\pN\s]+/u',
            ' ',
            $text
        );

        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        );

        return trim($text);
    }

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'category' => null,
            'priority' => null,
            'confidence' => 0,
            'reason' => null,
            'matchedIndicators' => [
                'category' => [],
                'priority' => [],
            ],
            'metadata' => [
                'engine' =>
                    'weighted_nlp_classifier',

                'generatedAt' =>
                    now()->toISOString(),
            ],
        ];
    }
}