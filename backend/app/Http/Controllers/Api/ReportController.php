<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TicketReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function ticketsPdf(
        Request $request,
        TicketReportService $reports
    ): Response {
        $tickets = $reports
            ->query($request, $request->user())
            ->get();

        $html = view('reports.tickets', [
            'tickets' => $tickets,
            'filters' => $request->only([
                'dateFrom',
                'dateTo',
            ]),
            'generatedAt' => now(),
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        $fileName =
            'tickets-report-'.now()->format('Y-m-d').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' =>
                'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function ticketsExcel(
        Request $request,
        TicketReportService $reports
    ): StreamedResponse {
        $tickets = $reports
            ->query($request, $request->user())
            ->get();

        $fileName =
            'tickets-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            function () use ($tickets): void {
                $output = fopen('php://output', 'wb');

                fwrite($output, "`xEF`xBB`xBF");

                fputcsv($output, [
                    'Ticket Number',
                    'Subject',
                    'Category',
                    'Priority',
                    'Status',
                    'Created By',
                    'Assigned To',
                    'Created At',
                    'Due At',
                    'Resolved At',
                ]);

                foreach ($tickets as $ticket) {
                    fputcsv($output, [
                        $ticket->ticketNumber,
                        $ticket->subject,
                        $ticket->category?->categoryName ?? '',
                        $ticket->priority?->priorityName ?? '',
                        $ticket->status?->statusName ?? '',
                        $this->userName($ticket->user),
                        $this->userName($ticket->assignedUser),
                        $ticket->createdAt?->format(
                            'Y-m-d H:i:s'
                        ) ?? '',
                        $ticket->dueAt?->format(
                            'Y-m-d H:i:s'
                        ) ?? '',
                        $ticket->resolvedAt?->format(
                            'Y-m-d H:i:s'
                        ) ?? '',
                    ]);
                }

                fclose($output);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]
        );
    }

    private function userName($user): string
    {
        if (! $user) {
            return 'Unassigned';
        }

        $name = trim(
            ($user->firstName ?? '').
            ' '.
            ($user->lastName ?? '')
        );

        return $name !== ''
            ? $name
            : ($user->email ?? 'Unknown');
    }
}
