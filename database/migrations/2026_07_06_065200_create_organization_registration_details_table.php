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
        Schema::create('organization_registration_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('organization_type'); // OrganizationType enum: co_curricular | extra_curricular
            $table->text('description');
            $table->string('contact_person');
            $table->string('contact_number');
            $table->string('contact_email');
            $table->date('date_organized');
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('roster')->nullable(); // list of member names
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_registration_details');
    }
};
