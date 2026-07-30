<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly TicketActivityService $activityService
    ) {
    }

    private function roleName(User $user): ?string
    {
        return $user->loadMissing('role')->role?->roleName;
    }

    private function canView(User $user, Ticket $ticket): bool
    {
        return match ($this->roleName($user)) {
            'Admin', 'Manager', 'SupportAgent' => true,
            'User' => (int) $ticket->userId === (int) $user->id,
            default => false,
        };
    }

    private function canUpload(User $user, Ticket $ticket): bool
    {
        return match ($this->roleName($user)) {
            'Admin' => true,
            'SupportAgent' =>
                (int) $ticket->assignedUserId === (int) $user->id,
            'User' => (int) $ticket->userId === (int) $user->id,
            default => false,
        };
    }

    private function isTerminal(Ticket $ticket): bool
    {
        $ticket->loadMissing('status');

        return in_array(
            $ticket->status?->statusName,
            ['Closed', 'Cancelled'],
            true
        );
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    public function index(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden(
                'You cannot view attachments on this ticket.'
            );
        }

        return response()->json([
            'success' => true,
            'data' => $ticket->attachments()
                ->with('uploadedBy.role')
                ->orderByDesc('createdAt')
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canUpload($user, $ticket)) {
            return $this->forbidden(
                'Only the ticket owner, assigned Support Agent, '
                . 'or an Admin can upload attachments.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Attachments cannot be added to a Closed '
                    . 'or Cancelled ticket.',
            ], 422);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,png,jpg,jpeg,txt',
            ],
        ]);

        $file = $request->file('file');

        $path = $file->store(
            "ticket-attachments/{$ticket->id}",
            'public'
        );

        $attachment = $ticket->attachments()->create([
            'uploadedByUserId' => $user->id,
            'fileName' => $file->getClientOriginalName(),
            'filePath' => $path,
            'fileType' => $file->getMimeType(),
            'fileSize' => $file->getSize(),
        ]);

        $this->activityService->record(
            $ticket,
            $user,
            'attachment_uploaded',
            'A ticket attachment was uploaded.',
            null,
            [
                'attachmentId' => $attachment->id,
                'fileName' => $attachment->fileName,
                'fileType' => $attachment->fileType,
                'fileSize' => $attachment->fileSize,
            ],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment->load('uploadedBy.role'),
        ], 201);
    }

    public function download(
        Request $request,
        Ticket $ticket,
        TicketAttachment $attachment
    ): StreamedResponse|JsonResponse {
        if (
            (int) $attachment->ticketId
            !== (int) $ticket->id
        ) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden(
                'You cannot download this attachment.'
            );
        }

        if (
            !Storage::disk('public')
                ->exists($attachment->filePath)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file was not found.',
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->filePath,
            $attachment->fileName
        );
    }

    public function destroy(
        Request $request,
        Ticket $ticket,
        TicketAttachment $attachment
    ): JsonResponse {
        if (
            (int) $attachment->ticketId
            !== (int) $ticket->id
        ) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $isAdmin = $this->roleName($user) === 'Admin';

        $isAuthorizedUploader =
            (int) $attachment->uploadedByUserId
                === (int) $user->id
            && $this->canUpload($user, $ticket);

        if (!$isAdmin && !$isAuthorizedUploader) {
            return $this->forbidden(
                'Only the currently authorized uploader or an '
                . 'Admin can delete this attachment.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Attachments on a Closed or Cancelled ticket '
                    . 'cannot be deleted.',
            ], 422);
        }

        $attachmentData = [
            'attachmentId' => $attachment->id,
            'fileName' => $attachment->fileName,
        ];

        Storage::disk('public')->delete(
            $attachment->filePath
        );

        $attachment->delete();

        $this->activityService->record(
            $ticket,
            $user,
            'attachment_deleted',
            'A ticket attachment was deleted.',
            $attachmentData,
            null,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}