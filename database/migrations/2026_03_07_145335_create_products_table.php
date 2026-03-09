<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            $table->text('description')->nullable();
            $table->longText('details')->nullable();

            $table->json('images')->nullable();

            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();

            $table->integer('stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('damaged_stock')->default(0);
            $table->integer('low_stock_threshold')->default(0);

            $table->decimal('weight', 12, 2)->nullable();
            $table->decimal('length', 12, 2)->nullable();
            $table->decimal('width', 12, 2)->nullable();
            $table->decimal('height', 12, 2)->nullable();

            $table->string('dimension_unit')->nullable()->default('in');
            $table->string('weight_unit')->nullable()->default('lb');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->string('status')->default('draft');
            // draft, active, inactive, archived

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};