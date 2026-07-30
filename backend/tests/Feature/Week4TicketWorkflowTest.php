<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PrioritySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week4TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Priority $priority;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CategorySeeder::class,
            PrioritySeeder::class,
            StatusSeeder::class,
        ]);

        $this->category = Category::query()->firstOrFail();
        $this->priority = Priority::query()->firstOrFail();
    }

    private function user(string $roleName): User
    {
        return User::factory()
            ->withRole($roleName)
            ->create();
    }

    private function ticket(
        User $owner,
        string $statusName = 'Open',
        ?User $agent = null
    ): Ticket {
        $status = Status::query()
            ->where('statusName', $statusName)
            ->firstOrFail();

        return Ticket::query()->create([
            'ticketNumber' =>
                'TCK-' . fake()->unique()->numerify('########'),
            'userId' => $owner->id,
            'assignedUserId' => $agent?->id,
            'categoryId' => $this->category->id,
            'priorityId' => $this->priority->id,
            'statusId' => $status->id,
            'subject' => 'Week 4 workflow ticket',
            'description' => 'Ticket used for Week 4 testing.',
            'dueAt' => now()->addDay(),
        ]);
    }

    public function test_assignment_and_reassignment_create_history_and_notifications(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $firstAgent = $this->user('SupportAgent');
        $secondAgent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $firstAgent->id]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.assignedUserId',
                $firstAgent->id
            );

        $this->assertDatabaseHas('ticketassignments', [
            'ticketId' => $ticket->id,
            'assignedUserId' => $firstAgent->id,
            'assignmentType' => 'assigned',
        ]);

        $this->assertDatabaseHas('activitylogs', [
            'ticketId' => $ticket->id,
            'action' => 'ticket_assigned',
        ]);

        $this->assertDatabaseHas('notifications', [
            'ticketId' => $ticket->id,
            'userId' => $firstAgent->id,
            'type' => 'ticket_assigned',
            'isRead' => false,
        ]);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $secondAgent->id]
        )->assertUnprocessable();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            [
                'assignedUserId' => $secondAgent->id,
                'reason' =>
                    'A different specialist is required.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.assignedUserId',
                $secondAgent->id
            );

        $this->assertDatabaseHas('ticketassignments', [
            'ticketId' => $ticket->id,
            'assignedUserId' => $secondAgent->id,
            'previousAssignedUserId' => $firstAgent->id,
            'assignmentType' => 'reassigned',
            'reason' =>
                'A different specialist is required.',
        ]);

        $this->assertDatabaseHas('activitylogs', [
            'ticketId' => $ticket->id,
            'action' => 'ticket_reassigned',
        ]);

        $this->assertDatabaseHas('notifications', [
            'ticketId' => $ticket->id,
            'userId' => $secondAgent->id,
            'type' => 'ticket_reassigned',
        ]);

        $this->assertDatabaseHas('notifications', [
            'ticketId' => $ticket->id,
            'userId' => $firstAgent->id,
            'type' => 'ticket_assignment_removed',
        ]);
    }

    public function test_work_sessions_pause_resume_and_stop_when_resolved(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);
        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $agent->id]
        )->assertOk();

        Sanctum::actingAs($agent);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/start"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status.statusName',
                'InProgress'
            );

        $this->assertDatabaseHas('ticketworksessions', [
            'ticketId' => $ticket->id,
            'userId' => $agent->id,
            'endedAt' => null,
        ]);

        $this->travel(5)->minutes();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/pause",
            ['reason' => 'Waiting for equipment.']
        )->assertOk();

        $this->assertDatabaseMissing('ticketworksessions', [
            'ticketId' => $ticket->id,
            'userId' => $agent->id,
            'endedAt' => null,
        ]);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/resume"
        )->assertOk();

        $this->travel(2)->minutes();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/resolve",
            [
                'resolutionNote' =>
                    'The equipment was replaced and tested.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status.statusName',
                'Resolved'
            )
            ->assertJsonPath(
                'data.resolutionNote',
                'The equipment was replaced and tested.'
            );

        $this->assertDatabaseCount('ticketworksessions', 2);

        $this->assertDatabaseMissing('ticketworksessions', [
            'ticketId' => $ticket->id,
            'endedAt' => null,
        ]);

        $this->assertDatabaseHas('activitylogs', [
            'ticketId' => $ticket->id,
            'action' => 'work_paused',
        ]);

        $this->assertDatabaseHas('activitylogs', [
            'ticketId' => $ticket->id,
            'action' => 'ticket_resolved',
        ]);
    }

    public function test_agent_can_escalate_with_reason_and_managers_are_notified(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $admin = $this->user('Admin');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);
        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $agent->id]
        )->assertOk();

        Sanctum::actingAs($agent);
        $this->patchJson(
            "/api/tickets/{$ticket->id}/start"
        )->assertOk();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/escalate",
            []
        )->assertUnprocessable();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/escalate",
            [
                'reason' =>
                    'Network infrastructure access is required.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status.statusName',
                'Escalated'
            )
            ->assertJsonPath(
                'data.escalationReason',
                'Network infrastructure access is required.'
            );

        $this->assertDatabaseHas('notifications', [
            'ticketId' => $ticket->id,
            'userId' => $manager->id,
            'type' => 'ticket_escalated',
        ]);

        $this->assertDatabaseHas('notifications', [
            'ticketId' => $ticket->id,
            'userId' => $admin->id,
            'type' => 'ticket_escalated',
        ]);

        $this->assertDatabaseMissing('ticketworksessions', [
            'ticketId' => $ticket->id,
            'endedAt' => null,
        ]);
    }

    public function test_ticket_owner_can_cancel_only_their_open_ticket_with_reason(): void
    {
        $owner = $this->user('User');
        $otherEmployee = $this->user('User');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($otherEmployee);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/cancel",
            ['reason' => 'Not needed anymore.']
        )->assertForbidden();

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/cancel",
            []
        )->assertUnprocessable();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/cancel",
            ['reason' => 'The request is no longer needed.']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status.statusName',
                'Cancelled'
            )
            ->assertJsonPath(
                'data.cancellationReason',
                'The request is no longer needed.'
            );

        $this->assertDatabaseHas('activitylogs', [
            'ticketId' => $ticket->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_historical_agent_can_view_but_cannot_work_on_reassigned_ticket(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $firstAgent = $this->user('SupportAgent');
        $secondAgent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);

        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $firstAgent->id]
        )->assertOk();

        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            [
                'assignedUserId' => $secondAgent->id,
                'reason' => 'Transferred to another specialist.',
            ]
        )->assertOk();

        Sanctum::actingAs($firstAgent);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath(
                'data.data.0.id',
                $ticket->id
            );

        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath(
                'permissions.isHistoricalAgent',
                true
            )
            ->assertJsonPath(
                'permissions.isCurrentAssignee',
                false
            );

        $this->patchJson(
            "/api/tickets/{$ticket->id}/start"
        )->assertForbidden();
    }

    public function test_subject_and_description_are_immutable(): void
    {
        $owner = $this->user('User');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/tickets/{$ticket->id}",
            [
                'categoryId' => $this->category->id,
                'priorityId' => $this->priority->id,
                'subject' => 'Changed subject',
                'description' => $ticket->description,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'subject' => 'Week 4 workflow ticket',
            'description' => 'Ticket used for Week 4 testing.',
        ]);
    }

    public function test_comment_replies_and_internal_note_privacy(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);
        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $agent->id]
        )->assertOk();

        Sanctum::actingAs($owner);

        $parentResponse = $this->postJson(
            "/api/tickets/{$ticket->id}/comments",
            ['comment' => 'Is there an update?']
        )
            ->assertCreated()
            ->assertJsonPath('data.parentCommentId', null);

        $parentId = $parentResponse->json('data.id');

        Sanctum::actingAs($agent);

        $this->postJson(
            "/api/tickets/{$ticket->id}/comments",
            [
                'comment' => 'We are investigating the issue.',
                'parentCommentId' => $parentId,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.parentCommentId',
                $parentId
            );

        $this->postJson(
            "/api/tickets/{$ticket->id}/comments",
            [
                'comment' => 'Private diagnostic details.',
                'isInternal' => true,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.isInternal', true);

        Sanctum::actingAs($owner);

        $this->getJson(
            "/api/tickets/{$ticket->id}/comments"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonPath(
                'data.0.replies.0.comment',
                'We are investigating the issue.'
            );
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = $this->user('User');
        $manager = $this->user('Manager');
        $agent = $this->user('SupportAgent');
        $otherAgent = $this->user('SupportAgent');
        $ticket = $this->ticket($owner);

        Sanctum::actingAs($manager);
        $this->patchJson(
            "/api/tickets/{$ticket->id}/assign",
            ['assignedUserId' => $agent->id]
        )->assertOk();

        $notification = $agent->appNotifications()
            ->where('type', 'ticket_assigned')
            ->firstOrFail();

        Sanctum::actingAs($otherAgent);

        $this->patchJson(
            "/api/notifications/{$notification->id}/read"
        )->assertForbidden();

        Sanctum::actingAs($agent);

        $this->patchJson(
            "/api/notifications/{$notification->id}/read"
        )
            ->assertOk()
            ->assertJsonPath('data.isRead', true);
    }
}