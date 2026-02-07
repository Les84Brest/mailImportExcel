<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lara_polcar_items', function (Blueprint $table) {
            // Основной идентификатор
            $table->id(); // Laravel автоматически создаст bigint unsigned NOT NULL AUTO_INCREMENT
            
            // Строковые поля
            $table->string('title');
            $table->string('part_title');
            $table->string('oem');
            $table->string('producer');
            
            // Внешний ключ
            $table->foreignId('polcar_car_id')
                  ->constrained('polcar_cars') // Укажите имя связанной таблицы
                  ->restrictOnDelete(); // или ->cascadeOnDelete() в зависимости от логики
            
            // JSON поля
            $table->json('table_info')->nullable();
            $table->json('images')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Индексы (дополнительно, для оптимизации)
            $table->index('oem');
            $table->index('producer');
            $table->index('polcar_car_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lara_polcar_items');
    }
};