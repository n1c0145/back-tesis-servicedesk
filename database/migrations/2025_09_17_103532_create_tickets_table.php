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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 10)->unique()->after('id');

            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->integer('time')->default(0);
            $table->integer('sla')->nullable();
            $table->timestamp('firstupdate')->nullable();


            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');

            $table->foreignId('closed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->foreignId('status_id')->constrained('ticket_statuses')->onDelete('cascade');
            $table->foreignId('priority_id')->nullable()->constrained('ticket_priorities')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
