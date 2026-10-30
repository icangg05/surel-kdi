<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->longText('body_html');
            // Pilihan penerima saat disusun: {groups: [id], contacts: [id]}
            $table->json('audience')->nullable();
            // draft | scheduled | sending | waiting_quota | paused | completed | cancelled
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Utas percakapan. `folder` adalah letak utas di kotak masuk; null = utas
        // yang baru berisi email keluar (hanya tampil di Terkirim) sampai ada balasan.
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->default('');
            // Disisipkan di Reply-To (lokal+token@inbound) supaya balasan pasti kembali ke utas ini.
            $table->string('reply_token', 24)->unique();
            $table->string('folder', 16)->nullable(); // inbox | archive | trash | spam
            $table->boolean('is_starred')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->index(['mailbox_id', 'folder', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // penulis (keluar)
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete(); // pengirim dikenal (masuk)
            $table->string('direction', 3); // in | out
            // draft | scheduled | queued | waiting_quota | sent | failed | received
            $table->string('status', 16)->index();
            $table->string('message_id_header')->nullable()->index();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject')->default('');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('snippet', 300)->default('');
            $table->unsignedBigInteger('size')->default(0);
            $table->decimal('spam_score', 6, 2)->nullable();
            $table->boolean('is_read')->default(true);
            $table->boolean('has_attachments')->default(false);
            $table->string('error')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['mailbox_id', 'direction', 'status', 'sent_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('messages', function (Blueprint $table) {
                $table->fullText(['subject', 'body_text']);
            });
        }

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pengunggah
            $table->string('filename');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path')->nullable(); // null = belum diunduh dari Brevo
            $table->string('content_id')->nullable();
            $table->string('download_token', 512)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        // Satu baris per alamat penerima per email keluar — dasar pelacakan status
        // dari webhook Brevo dan hitungan kredit.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained()->nullOnDelete(); // utas balasan siaran
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('kind', 4)->default('to'); // to | cc | bcc
            // pending | queued | sent | delivered | opened | clicked | soft_bounce | hard_bounce | blocked | spam | failed | skipped | cancelled
            $table->string('status', 16)->default('pending');
            $table->string('reply_token', 24)->nullable()->unique();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
            $table->index(['message_id', 'email']);
        });

        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['delivery_id', 'event', 'occurred_at']);
        });

        // Payload mentah webhook inbound, satu baris per email (Uuid Brevo) — audit & proses ulang.
        Schema::create('inbound_payloads', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->json('payload');
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        // Pemakaian kredit Brevo per hari (zona waktu reset kuota). Dikunci baris saat reservasi.
        Schema::create('quota_days', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('used')->default(0);
            $table->boolean('exhausted')->default(false); // Brevo menolak karena limit, walau hitungan kita belum penuh
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_days');
        Schema::dropIfExists('inbound_payloads');
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('threads');
        Schema::dropIfExists('broadcasts');
    }
};
