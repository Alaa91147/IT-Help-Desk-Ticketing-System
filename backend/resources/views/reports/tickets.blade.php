<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Tickets Report</title>

    <style>
        @page {
            margin: 25px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2937;
            font-size: 10px;
            margin: 0;
        }

        h1 {
            margin: 0 0 6px;
            color: #111827;
            font-size: 22px;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: collapse;
        }

        .summary td {
            width: 33.33%;
            padding: 10px;
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
        }

        .summary-label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
        }

        .summary-value {
            margin-top: 4px;
            color: #111827;
            font-size: 13px;
            font-weight: bold;
        }

        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .tickets-table thead {
            display: table-header-group;
        }

        .tickets-table th {
            padding: 7px 5px;
            color: #ffffff;
            background-color: #2563eb;
            border: 1px solid #1d4ed8;
            font-size: 9px;
            text-align: left;
        }

        .tickets-table td {
            padding: 6px 5px;
            border: 1px solid #d1d5db;
            vertical-align: top;
            word-wrap: break-word;
        }

        .tickets-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .empty-message {
            padding: 25px;
            color: #6b7280;
            text-align: center;
        }

        .footer {
            margin-top: 15px;
            color: #6b7280;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>

<body>
    @php
        $displayUserName = function ($user) {
            if (!$user) {
                return 'Unassigned';
            }

            $name = trim(
                ($user->firstName ?? '') . ' ' .
                ($user->lastName ?? '')
            );

            return $name !== ''
                ? $name
                : ($user->email ?? 'Unknown');
        };

        $dateFrom = $filters['dateFrom'] ?? 'All dates';
        $dateTo = $filters['dateTo'] ?? 'Present';
    @endphp

    <h1>Tickets Report</h1>

    <div class="subtitle">
        Generated on {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="summary-label">Total tickets</div>
                <div class="summary-value">{{ $tickets->count() }}</div>
            </td>

            <td>
                <div class="summary-label">Date from</div>
                <div class="summary-value">{{ $dateFrom }}</div>
            </td>

            <td>
                <div class="summary-label">Date to</div>
                <div class="summary-value">{{ $dateTo }}</div>
            </td>
        </tr>
    </table>

    <table class="tickets-table">
        <thead>
            <tr>
                <th style="width: 8%;">Number</th>
                <th style="width: 15%;">Subject</th>
                <th style="width: 9%;">Category</th>
                <th style="width: 8%;">Priority</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 11%;">Created By</th>
                <th style="width: 11%;">Assigned To</th>
                <th style="width: 10%;">Created At</th>
                <th style="width: 10%;">Due At</th>
                <th style="width: 10%;">Resolved At</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td>{{ $ticket->ticketNumber }}</td>

                    <td>{{ $ticket->subject }}</td>

                    <td>
                        {{ $ticket->category?->categoryName ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $ticket->priority?->priorityName ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $ticket->status?->statusName ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $displayUserName($ticket->user) }}
                    </td>

                    <td>
                        {{ $displayUserName($ticket->assignedUser) }}
                    </td>

                    <td>
                        {{ $ticket->createdAt?->format('Y-m-d H:i') ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $ticket->dueAt?->format('Y-m-d H:i') ?? 'N/A' }}
                    </td>

                    <td>
                        {{ $ticket->resolvedAt?->format('Y-m-d H:i') ?? 'N/A' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty-message">
                        No tickets matched the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        IT Help Desk Ticketing System - Confidential Report
    </div>
</body>
</html>