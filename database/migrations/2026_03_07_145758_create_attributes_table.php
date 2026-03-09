<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('type')->default('text');
            // text, number, select, boolean, textarea, date

            $table->string('unit')->nullable();
            // in, cm, lb, kg, etc.

            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_variant')->default(false);
            $table->boolean('is_system')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('status')->default('active');
            // active, inactive

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};