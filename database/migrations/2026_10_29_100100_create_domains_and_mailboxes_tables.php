<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            // Domain pengirim, harus terautentikasi di Brevo (SPF/DKIM).
            $table->string('name')->unique();
            // Subdomain penerima yang MX-nya ke Brevo. Brevo mewajibkan beda dari domain pengirim.
            $table->string('inbound_domain')->unique();
            $table->boolean('is_active')->default(true);
            // Hasil cek DNS terakhir: {spf: bool, dkim: bool, dmarc: bool, mx: bool, records: {...}}
            $table->json('dns_status')->nullable();
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('local_part', 64);
            $table->string('display_name');
            $table->text('signature_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['domain_id', 'local_part']);
        });

        // Operator yang boleh memakai mailbox; satu mailbox bisa dipakai bersama.
        Schema::create('mailbox_user', function (Blueprint $table) {
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['mailbox_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_user');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('domains');
    }
};
