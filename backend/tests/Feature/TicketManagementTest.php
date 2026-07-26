<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PrioritySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketManagementTest extends TestCase
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
        ?User $agent = null,
        string $subject = 'Printer is not working'
    ): Ticket {
        $status = Status::query()
            ->where('statusName', $statusName)
            ->firstOrFail();

        return Ticket::query()->create([
            'ticketNumber' => 'TCK-' . fake()->unique()->numerify('########'),
            'userId' => $owner->id,
            'assignedUserId' => $agent?->id,
            'categoryId' => $this->category->id,
            'priorityId' => $this->priority->id,
            'statusId' => $status->id,
            'subject' => $subject,
            'description' => 'Feature test ticket description.',
        ]);
    }

    public function test_employee_only_lists_their_own_tickets(): void
    {
        $employee = $this->user('User');
        $otherEmployee = $this->user('User');

        $ownTicket = $this->ticket($employee);
        $this->ticket($otherEmployee);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/tickets');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $ownTicket->id);
    }

    public function test_support_agent_only_lists_assigned_tickets(): void
    {
        $employee = $this->user('User');
        $agent = $this->user('SupportAgent');
        $otherAgent = $this->user('SupportAgent');

        $assignedTicket = $this->ticket($employee, 'Assigned', $agent);
        $this->ticket($employee, 'Assigned', $otherAgent);

        Sanctum::actingAs($agent);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $assignedTicket->id);
    }

    public function test_manager_can_view_all_tickets_and_assign_an_agent(): void
    {
        $employee = $this->user('User');
        $manager = $this->user('Manager');
        $agent = $this->user('SupportAgent');

        $ticket = $this->ticket($employee);
        $this->ticket($this->user('User'));

        Sanctum::actingAs($manager);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->patchJson("/api/tickets/{$ticket->id}/assign", [
            'assignedUserId' => $agent->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignedUserId', $agent->id)
            ->assertJsonPath('data.status.statusName', 'Assigned');

        $this->assertDatabaseHas('ticketassignments', [
            'ticketId' => $ticket->id,
            'assignedUserId' => $agent->id,
            'assignedByUserId' => $manager->id,
        ]);
    }

    public function test_employee_cannot_assign_a_ticket(): void
    {
        $employee = $this->user('User');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($employee);

        Sanctum::actingAs($employee);

        $this->patchJson("/api/tickets/{$ticket->id}/assign", [
            'assignedUserId' => $agent->id,
        ])->assertForbidden();
    }

    public function test_ticket_follows_assigned_in_progress_resolved_closed_workflow(): void
    {
        $employee = $this->user('User');
        $manager = $this->user('Manager');
        $agent = $this->user('SupportAgent');
        $admin = $this->user('Admin');
        $ticket = $this->ticket($employee);

        Sanctum::actingAs($manager);
        $this->patchJson("/api/tickets/{$ticket->id}/assign", [
            'assignedUserId' => $agent->id,
        ])->assertOk();

        Sanctum::actingAs($agent);
        $this->patchJson("/api/tickets/{$ticket->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status.statusName', 'InProgress');

        $this->patchJson("/api/tickets/{$ticket->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status.statusName', 'Resolved');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/tickets/{$ticket->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status.statusName', 'Closed');
    }

    public function test_employee_cannot_edit_a_ticket_after_assignment(): void
    {
        $employee = $this->user('User');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($employee, 'Assigned', $agent);

        Sanctum::actingAs($employee);

        $this->putJson("/api/tickets/{$ticket->id}", [
            'categoryId' => $this->category->id,
            'priorityId' => $this->priority->id,
            'subject' => 'Changed subject',
            'description' => 'Changed description',
        ])->assertForbidden();
    }

    public function test_only_manager_and_admin_can_view_ticket_report(): void
    {
        $employee = $this->user('User');
        $manager = $this->user('Manager');
        $this->ticket($employee);

        Sanctum::actingAs($employee);
        $this->getJson('/api/manager/reports/tickets')
            ->assertForbidden();

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/reports/tickets')
            ->assertOk()
            ->assertJsonPath('data.totals.tickets', 1)
            ->assertJsonPath('data.totals.unassigned', 1);
    }

    public function test_employee_cannot_see_internal_agent_comment(): void
    {
        $employee = $this->user('User');
        $agent = $this->user('SupportAgent');
        $ticket = $this->ticket($employee, 'Assigned', $agent);

        $ticket->comments()->create([
            'userId' => $agent->id,
            'comment' => 'Internal diagnostic note.',
            'isInternal' => true,
        ]);

        $ticket->comments()->create([
            'userId' => $agent->id,
            'comment' => 'Visible update.',
            'isInternal' => false,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Visible update.');
    }

    public function test_ticket_owner_can_upload_and_delete_an_attachment(): void
    {
        Storage::fake('public');

        $employee = $this->user('User');
        $ticket = $this->ticket($employee);

        Sanctum::actingAs($employee);

        $response = $this->postJson(
            "/api/tickets/{$ticket->id}/attachments",
            ['file' => UploadedFile::fake()->create('error.pdf', 100, 'application/pdf')]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.fileName', 'error.pdf');

        $attachmentId = $response->json('data.id');
        $filePath = $response->json('data.filePath');

        Storage::disk('public')->assertExists($filePath);

        $this->deleteJson(
            "/api/tickets/{$ticket->id}/attachments/{$attachmentId}"
        )->assertOk();

        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $admin = $this->user('Admin');
        $employee = $this->user('User');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$employee->id}/role", [
            'roleName' => 'Manager',
        ])
            ->assertOk()
            ->assertJsonPath('data.role.roleName', 'Manager');

        $managerRoleId = Role::query()
            ->where('roleName', 'Manager')
            ->value('id');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'roleId' => $managerRoleId,
        ]);
    }
}