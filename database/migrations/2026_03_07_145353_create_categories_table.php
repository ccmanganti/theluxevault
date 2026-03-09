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

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('type');
            // room, style, extra, product_type

            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('status')->default('active');
            // active, inactive

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'slug', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};