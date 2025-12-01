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
    Schema::create('knowledge_base', function (Blueprint $table) {
        $table->id();

        $table->foreignId('ticket_id')
              ->nullable()
              ->constrained('tickets')
              ->onDelete('set null');

        $table->string('ticket_number', 10)->nullable();

        $table->string('titulo');
        $table->longText('descripcion')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
