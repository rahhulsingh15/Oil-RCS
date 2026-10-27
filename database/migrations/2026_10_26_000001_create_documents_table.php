<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Document Repository: files uploaded per block (WCR, RSC, reports, letters, MC minutes ...) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mc_meeting_id')->nullable()->constrained()->nullOnDelete(); // set for MC minutes
            $table->string('type', 50);                 // WCR / RSC / Study Report / Letter / MC Minutes / Other
            $table->string('title');
            $table->date('document_date')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');                // inside storage/app/private, never public
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');         // bytes
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['block_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
