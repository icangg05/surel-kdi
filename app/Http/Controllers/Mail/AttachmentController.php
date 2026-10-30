<?php

namespace App\Http\Controllers\Mail;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Broadcast;
use App\Models\Message;
use App\Support\MailPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxMb = (int) config('kdimail.max_attachments_mb');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.($maxMb * 1024)],
            'message_id' => ['required_without:broadcast_id', 'nullable', 'integer'],
            'broadcast_id' => ['required_without:message_id', 'nullable', 'integer'],
        ]);

        $owner = isset($data['message_id'])
            ? Message::query()->findOrFail((int) $data['message_id'])
            : Broadcast::query()->findOrFail((int) $data['broadcast_id']);

        $this->authorizeMailbox($request, $owner->mailbox);
        abort_if($owner instanceof Message && $owner->status !== MessageStatus::Draft, 422);
        abort_if($owner instanceof Broadcast && ! $owner->isEditable(), 422);

        $file = $request->file('file');

        if ($owner->attachments()->sum('size') + $file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => "Total lampiran tidak boleh lebih dari $maxMb MB."]);
        }

        $path = $file->storeAs('attachments/'.now()->format('Y/m'), (string) Str::uuid(), Attachment::DISK);

        $attachment = $owner->attachments()->create([
            'user_id' => $request->user()->id,
            'filename' => mb_substr($file->getClientOriginalName(), 0, 250),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'path' => $path,
        ]);

        return response()->json(MailPresenter::attachment($attachment));
    }

    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $attachment);
        abort_unless($attachment->isAvailable(), 404, 'Lampiran belum tersedia.');

        return Storage::disk(Attachment::DISK)->download((string) $attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Gambar inline (cid:) di isi email. Hanya image/* yang boleh tampil inline. */
    public function inline(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $attachment);
        abort_unless($attachment->isAvailable() && str_starts_with($attachment->mime, 'image/') && $attachment->mime !== 'image/svg+xml', 404);

        return Storage::disk(Attachment::DISK)->response((string) $attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);
        abort_if($attachment->message && $attachment->message->status !== MessageStatus::Draft, 422);
        abort_if($attachment->broadcast && ! $attachment->broadcast->isEditable(), 422);

        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    private function authorizeAttachment(Request $request, Attachment $attachment): void
    {
        $this->authorizeMailbox($request, $attachment->message?->mailbox ?? $attachment->broadcast?->mailbox);
    }
}
