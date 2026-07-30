<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'statusName' => 'Open',
                'description' => 'Ticket has been created',
            ],
            [
                'statusName' => 'Assigned',
                'description' => 'Ticket has been assigned to an agent',
            ],
            [
                'statusName' => 'InProgress',
                'description' => 'Ticket is currently being handled',
            ],
            [
                'statusName' => 'Escalated',
                'description' =>
                    'Ticket requires manager review and reassignment',
            ],
            [
                'statusName' => 'Resolved',
                'description' => 'Issue has been resolved',
            ],
            [
                'statusName' => 'Closed',
                'description' => 'Ticket has been closed',
            ],
            [
                'statusName' => 'Cancelled',
                'description' =>
                    'Ticket was cancelled with a recorded reason',
            ],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['statusName' => $status['statusName']],
                [
                    'description' => $status['description'],
                    'isActive' => true,
                ]
            );
        }
    }
}