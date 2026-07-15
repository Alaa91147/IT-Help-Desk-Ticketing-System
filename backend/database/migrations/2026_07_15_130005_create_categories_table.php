<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('categoryName')->unique();
            $table->string('description')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};