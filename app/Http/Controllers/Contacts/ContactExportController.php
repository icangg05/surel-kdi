<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $group = $request->integer('group') ?: null;
        $name = 'kontak-'.now()->format('Ymd').'.xlsx';
        activity('audit')->causedBy($request->user())->withProperties(['group' => $group])->log('Ekspor kontak');

        return response()->streamDownload(function () use ($group, $name) {
            // Tulis ke php://output lewat StreamedResponse Laravel, bukan toBrowser() yang memanggil exit.
            $writer = SimpleExcelWriter::streamDownload($name, 'xlsx', fn ($w) => $w->openToFile('php://output'));

            Contact::query()->with('groups:id,name')
                ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
                ->orderBy('name')
                ->lazy()
                ->each(fn (Contact $c) => $writer->addRow([
                    'Nama' => $c->name,
                    'Email' => $c->email,
                    'Jabatan' => $c->position,
                    'Instansi' => $c->organization,
                    'Telepon' => $c->phone,
                    'Grup' => $c->groups->pluck('name')->implode(', '),
                    'Status' => $c->status->label(),
                ]));

            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
