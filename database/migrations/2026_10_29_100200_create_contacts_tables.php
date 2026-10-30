<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('position')->nullable();      // jabatan
            $table->string('organization')->nullable();  // instansi / OPD
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            // active | bounced | complained | blocked — selain active tidak ikut siaran.
            $table->string('status', 16)->default('active')->index();
            $table->string('suppressed_reason')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization');
        });

        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('color', 16)->default('slate');
            $table->timestamps();
        });

        Schema::create('contact_contact_group', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'contact_group_id']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
        Schema::dropIfExists('contact_contact_group');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
    }
};
