<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('mail_id')->unique();
            $table->string('filename');
            $table->integer('total_items')->default(0);
            $table->integer('created_count')->default(0);
            $table->integer('deleted_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['mail_id']);
        });


    }

    public function down()
    {
        Schema::dropIfExists('import_histories');
    }
};
