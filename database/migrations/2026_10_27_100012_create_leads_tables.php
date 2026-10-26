<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 20);

            foreach (['from', 'to'] as $prefix) {
                $table->string("{$prefix}_address");
                $table->string("{$prefix}_city")->nullable();
                $table->char("{$prefix}_state", 2)->nullable();
                $table->string("{$prefix}_zip", 10)->nullable();
                $table->decimal("{$prefix}_lat", 10, 7)->nullable();
                $table->decimal("{$prefix}_lng", 10, 7)->nullable();
            }

            $table->date('move_date');
            $table->foreignId('move_type_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot so the lead stays readable if the move type is renamed or deleted.
            $table->string('move_type_name');
            $table->string('status', 20)->default('new')->index();
            $table->string('source_url', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->json('utm')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->text('message');
            $table->string('source_url', 2048)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
    }
};
