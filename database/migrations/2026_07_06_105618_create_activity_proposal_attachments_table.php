<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_proposal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_proposal_attachments');
    }
};
