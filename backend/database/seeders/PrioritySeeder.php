<?php

namespace Database\Seeders;

use App\Models\Priority;
use Illuminate\Database\Seeder;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            [
                'priorityName' => 'Low',
                'description' => 'Minor issue with little impact',
                'priorityLevel' => 1,
            ],
            [
                'priorityName' => 'Medium',
                'description' => 'Normal issue requiring attention',
                'priorityLevel' => 2,
            ],
            [
                'priorityName' => 'High',
                'description' => 'Important issue affecting work',
                'priorityLevel' => 3,
            ],
            [
                'priorityName' => 'Urgent',
                'description' => 'Critical issue requiring immediate attention',
                'priorityLevel' => 4,
            ],
        ];

        foreach ($priorities as $priority) {
            Priority::updateOrCreate(
                ['priorityName' => $priority['priorityName']],
                [
                    'description' => $priority['description'],
                    'priorityLevel' => $priority['priorityLevel'],
                    'isActive' => true,
                ]
            );
        }
    }
}