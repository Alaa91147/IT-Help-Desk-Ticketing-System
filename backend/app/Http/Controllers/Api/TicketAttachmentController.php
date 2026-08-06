<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
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
        return $user
            ->loadMissing('role')
            ->role?->roleName;
    }

    private function canView(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            'Admin', 'Manager', 'SupportAgent' => true,
            'User' =>
                (int) $ticket->userId
                === (int) $user->id,
            default => false,
        };
    }

    private function canUpload(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            'Admin', 'Manager' => true,
            'SupportAgent' =>
                (int) $ticket->assignedUserId
                === (int) $user->id,
            'User' =>
                (int) $ticket->userId
                === (int) $user->id,
            default => false,
        };
    }

    private function canViewAttachment(
        User $user,
        Ticket $ticket,
        TicketAttachment $attachment
    ): bool {
        if (!$this->canView($user, $ticket)) {
            return false;
        }

        $attachment->loadMissing('comment');

        $isInternalComment = (bool) (
            $attachment->comment?->isInternal ?? false
        );

        return !$isInternalComment
            || $this->roleName($user) !== 'User';
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

    private function forbidden(
        string $message
    ): JsonResponse {
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
                ->whereNull('commentId')
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
                . 'Manager, or Admin can upload attachments.'
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

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,png,jpg,jpeg,txt',
            ],
            'commentId' => [
                'nullable',
                'integer',
                'exists:ticketcomments,id',
            ],
        ]);

        $comment = null;

        if (!empty($validated['commentId'])) {
            $comment = TicketComment::query()
                ->findOrFail($validated['commentId']);

            if (
                (int) $comment->ticketId
                !== (int) $ticket->id
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The comment does not belong '
                        . 'to this ticket.',
                ], 422);
            }

            if (
                $comment->isInternal
                && $this->roleName($user) === 'User'
            ) {
                return $this->forbidden(
                    'You cannot attach files '
                    . 'to an internal note.'
                );
            }

            $isManagement = in_array(
                $this->roleName($user),
                ['Admin', 'Manager'],
                true
            );

            if (
                !$isManagement
                && (int) $comment->userId
                    !== (int) $user->id
            ) {
                return $this->forbidden(
                    'You can only attach files '
                    . 'to your own comment.'
                );
            }
        }

        $file = $request->file('file');

        $folder = $comment
            ? "comment-attachments/{$comment->id}"
            : "ticket-attachments/{$ticket->id}";

        $path = $file->store($folder, 'public');

        $attachment = $ticket
            ->attachments()
            ->create([
                'commentId' => $comment?->id,
                'uploadedByUserId' => $user->id,
                'fileName' =>
                    $file->getClientOriginalName(),
                'filePath' => $path,
                'fileType' => $file->getMimeType(),
                'fileSize' => $file->getSize(),
            ]);

        $this->activityService->record(
            $ticket,
            $user,
            $comment
                ? 'comment_attachment_uploaded'
                : 'attachment_uploaded',
            $comment
                ? 'A comment attachment was uploaded.'
                : 'A ticket attachment was uploaded.',
            null,
            [
                'attachmentId' => $attachment->id,
                'commentId' => $attachment->commentId,
                'fileName' => $attachment->fileName,
                'fileType' => $attachment->fileType,
                'fileSize' => $attachment->fileSize,
            ],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => $comment
                ? 'Comment attachment uploaded successfully.'
                : 'Attachment uploaded successfully.',
            'data' => $attachment->load([
                'uploadedBy.role',
                'comment',
            ]),
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

        if (
            !$this->canViewAttachment(
                $user,
                $ticket,
                $attachment
            )
        ) {
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
                'message' =>
                    'Attachment file was not found.',
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

        $isManagement = in_array(
            $this->roleName($user),
            ['Admin', 'Manager'],
            true
        );

        $isAuthorizedUploader =
            (int) $attachment->uploadedByUserId
                === (int) $user->id
            && $this->canUpload($user, $ticket);

        if (
            !$isManagement
            && !$isAuthorizedUploader
        ) {
            return $this->forbidden(
                'Only the currently authorized uploader, '
                . 'Manager, or Admin can delete this attachment.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Attachments on a Closed or Cancelled '
                    . 'ticket cannot be deleted.',
            ], 422);
        }

        $attachmentData = [
            'attachmentId' => $attachment->id,
            'commentId' => $attachment->commentId,
            'fileName' => $attachment->fileName,
        ];

        Storage::disk('public')->delete(
            $attachment->filePath
        );

        $isCommentAttachment =
            $attachment->commentId !== null;

        $attachment->delete();

        $this->activityService->record(
            $ticket,
            $user,
            $isCommentAttachment
                ? 'comment_attachment_deleted'
                : 'attachment_deleted',
            $isCommentAttachment
                ? 'A comment attachment was deleted.'
                : 'A ticket attachment was deleted.',
            $attachmentData,
            null,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => $isCommentAttachment
                ? 'Comment attachment deleted successfully.'
                : 'Attachment deleted successfully.',
        ]);
    }
}