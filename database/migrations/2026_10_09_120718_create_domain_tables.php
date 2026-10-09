<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('currency', 3);
            foreach (array_diff(array_keys(Setting::DEFAULTS), ['currency']) as $field) {
                $this->decimal($table, $field);
            }
            $table->timestamps();
        });
        DB::table('settings')->insert(['id' => 1, ...Setting::DEFAULTS, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('filaments', function (Blueprint $table): void {
            $table->id();
            $table->string('brand');
            $table->string('material');
            $table->string('color');
            $this->decimal($table, 'purchase_price');
            $this->decimal($table, 'spool_weight');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image', 2048)->nullable();
            $table->boolean('active')->default(true);
            $this->decimal($table, 'selling_price')->default('0');
            $this->decimal($table, 'additional_material_cost')->default('0');
            $table->unsignedInteger('batch_quantity')->default(1);
            $this->decimal($table, 'batch_weight')->default('0');
            $table->unsignedInteger('batch_seconds')->default(0);
            foreach (array_diff(array_keys(Setting::DEFAULTS), ['currency']) as $field) {
                $this->decimal($table, $field.'_override')->nullable();
            }
            $table->timestamps();
        });
        Schema::create('product_filaments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filament_id')->constrained()->restrictOnDelete();
            $this->decimal($table, 'grams');
            $table->unique(['product_id', 'filament_id']);
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->string('category');
            $this->decimal($table, 'amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->date('sale_date');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $this->decimal($table, 'unit_price');
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['sales', 'expenses', 'product_filaments', 'products', 'filaments', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function decimal(Blueprint $table, string $name): ColumnDefinition
    {
        // SQLite's NUMERIC affinity converts precise decimals to floating point.
        return DB::getDriverName() === 'sqlite'
            ? $table->string($name, 25)
            : $table->decimal($name, 24, 12);
    }
};
