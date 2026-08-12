<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Collection;

class TicketDuplicateDetector
{
    private const DUPLICATE_THRESHOLD = 0.65;
    private const SUGGESTION_THRESHOLD = 0.35;

    /**
     * Check an already-created ticket.
     */
    public function detect(
        Ticket $ticket,
        int $limit = 100
    ): array {
        $matches = $this->findMatches(
            $ticket->subject,
            $ticket->description,
            $ticket->id,
            $limit
        );

        $bestMatch = $matches->first();

        return [
            'duplicate_id' =>
                $bestMatch &&
                $bestMatch['score'] >=
                    self::DUPLICATE_THRESHOLD
                    ? $bestMatch['ticket']['id']
                    : null,

            'score' => $bestMatch['score'] ?? 0,

            'ticket' => $bestMatch['ticket'] ?? null,

            'matches' => $matches->values()->all(),
        ];
    }

    /**
     * Check ticket information before creation.
     */
    public function check(
        string $subject,
        string $description,
        int $limit = 100
    ): array {
        $matches = $this->findMatches(
            $subject,
            $description,
            null,
            $limit
        );

        $bestMatch = $matches->first();

        return [
            'hasPotentialDuplicate' =>
                $bestMatch !== null,

            'hasStrongDuplicate' =>
                ($bestMatch['score'] ?? 0) >=
                    self::DUPLICATE_THRESHOLD,

            'bestScore' =>
                $bestMatch['score'] ?? 0,

            'matches' =>
                $matches->values()->all(),
        ];
    }

    private function findMatches(
        string $subject,
        string $description,
        ?int $excludedTicketId,
        int $limit
    ): Collection {
        $normalizedSubject =
            $this->normalize($subject);

        $normalizedDescription =
            $this->normalize($description);

        $candidates = Ticket::query()
            ->with([
                'status:id,statusName',
                'category:id,categoryName',
                'priority:id,priorityName',
                'assignedUser:id,firstName,lastName,email',
            ])
            ->when(
                $excludedTicketId,
                fn ($query) =>
                    $query->where(
                        'id',
                        '!=',
                        $excludedTicketId
                    )
            )
            ->whereNotNull('subject')
            ->orderByDesc('createdAt')
            ->limit($limit)
            ->get();

        return $candidates
            ->map(function (Ticket $candidate) use (
                $normalizedSubject,
                $normalizedDescription
            ): array {
                $subjectScore = $this->similarity(
                    $normalizedSubject,
                    $this->normalize(
                        $candidate->subject
                    )
                );

                $descriptionScore = $this->similarity(
                    $normalizedDescription,
                    $this->normalize(
                        $candidate->description
                    )
                );

                // Subject is more important than description.
                $score =
                    ($subjectScore * 0.65) +
                    ($descriptionScore * 0.35);

                $statusName =
                    $candidate->status?->statusName;

                $wasResolved = in_array(
                    $statusName,
                    ['Resolved', 'Closed'],
                    true
                );

                return [
                    'score' => round($score, 4),
                    'percentage' => round(
                        $score * 100,
                        1
                    ),
                    'subjectScore' => round(
                        $subjectScore,
                        4
                    ),
                    'descriptionScore' => round(
                        $descriptionScore,
                        4
                    ),
                    'wasResolved' => $wasResolved,

                    'ticket' => [
                        'id' => $candidate->id,
                        'ticketNumber' =>
                            $candidate->ticketNumber,
                        'subject' =>
                            $candidate->subject,
                        'description' =>
                            $candidate->description,
                        'status' =>
                            $candidate->status,
                        'category' =>
                            $candidate->category,
                        'priority' =>
                            $candidate->priority,
                        'assignedUser' =>
                            $candidate->assignedUser,
                        'resolutionNote' =>
                            $candidate->resolutionNote,
                        'resolvedAt' =>
                            $candidate->resolvedAt,
                        'closedAt' =>
                            $candidate->closedAt,
                        'createdAt' =>
                            $candidate->createdAt,
                    ],
                ];
            })
            ->filter(
                fn (array $match): bool =>
                    $match['score'] >=
                    self::SUGGESTION_THRESHOLD
            )
            ->sort(function (
                array $left,
                array $right
            ): int {
                // Similarity remains the main ordering factor.
                $scoreComparison =
                    $right['score'] <=>
                    $left['score'];

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                // Prefer resolved history when scores are equal.
                return $right['wasResolved'] <=>
                    $left['wasResolved'];
            })
            ->take(5);
    }

    private function similarity(
        string $first,
        string $second
    ): float {
        if ($first === '' || $second === '') {
            return 0;
        }

        $characterScore =
            $this->characterSimilarity(
                $first,
                $second
            );

        $wordScore =
            $this->wordSimilarity(
                $first,
                $second
            );

        // Word overlap is less affected by different word order.
        return max(
            $characterScore,
            ($characterScore * 0.4) +
                ($wordScore * 0.6)
        );
    }

    private function characterSimilarity(
        string $first,
        string $second
    ): float {
        $percentage = 0;

        similar_text(
            $first,
            $second,
            $percentage
        );

        return $percentage / 100;
    }

    private function wordSimilarity(
        string $first,
        string $second
    ): float {
        $firstWords = array_unique(
            array_filter(explode(' ', $first))
        );

        $secondWords = array_unique(
            array_filter(explode(' ', $second))
        );

        if (
            count($firstWords) === 0 ||
            count($secondWords) === 0
        ) {
            return 0;
        }

        $intersection = array_intersect(
            $firstWords,
            $secondWords
        );

        $union = array_unique(
            array_merge(
                $firstWords,
                $secondWords
            )
        );

        return count($intersection) /
            count($union);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

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
}