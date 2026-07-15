<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['categoryName' => 'Hardware', 'description' => 'Computer and device hardware issues'],
            ['categoryName' => 'Software', 'description' => 'Application and operating system issues'],
            ['categoryName' => 'Network', 'description' => 'Internet and network connectivity issues'],
            ['categoryName' => 'Account', 'description' => 'User account and access issues'],
            ['categoryName' => 'Email', 'description' => 'Email-related issues'],
            ['categoryName' => 'Other', 'description' => 'Other technical support requests'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['categoryName' => $category['categoryName']],
                [
                    'description' => $category['description'],
                    'isActive' => true,
                ]
            );
        }
    }
}