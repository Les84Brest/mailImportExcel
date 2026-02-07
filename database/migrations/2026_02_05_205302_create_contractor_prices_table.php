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
        Schema::create('contractor_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contractor_id')->default(11);
            $table->string('article_id', 100);
            $table->decimal('price', 10, 2);
            $table->integer('amount')->default(0);
            $table->date('delivery_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_prices');
    }
};
