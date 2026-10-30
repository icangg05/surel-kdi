<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Impor kontak dari CSV/XLSX dua langkah: pratinjau (validasi, deteksi
 * duplikat) lalu simpan. Kolom dikenali dari judulnya, tidak harus berurutan.
 */
class ContactImportController extends Controller
{
    /** Judul kolom yang diterima → field kontak. */
    private const COLUMNS = [
        'name' => ['nama', 'name', 'nama lengkap'],
        'email' => ['email', 'e-mail', 'alamat email', 'surel'],
        'position' => ['jabatan', 'position', 'posisi'],
        'organization' => ['instansi', 'opd', 'organisasi', 'organization', 'unit kerja', 'dinas'],
        'phone' => ['telepon', 'hp', 'no hp', 'no. hp', 'phone', 'whatsapp', 'wa'],
        'groups' => ['grup', 'group', 'kelompok'],
    ];

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']]);
        $rows = $this->read($request->file('file'));

        $existing = Contact::query()->whereIn('email', array_column($rows, 'email'))->pluck('email')->flip();
        $seen = [];
        $result = ['new' => 0, 'update' => 0, 'invalid' => 0, 'duplicate' => 0, 'rows' => []];

        foreach ($rows as $i => $row) {
            $status = match (true) {
                ! filter_var($row['email'], FILTER_VALIDATE_EMAIL) => 'invalid',
                isset($seen[$row['email']]) => 'duplicate',
                isset($existing[$row['email']]) => 'update',
                default => 'new',
            };
            $seen[$row['email']] = true;
            $result[$status]++;

            if (count($result['rows']) < 200 || $status === 'invalid') {
                $result['rows'][] = [...$row, 'line' => $i + 2, 'status' => $status];
            }
        }

        $result['total'] = count($rows);

        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'update_existing' => ['boolean'],
            'group_id' => ['nullable', 'integer', 'exists:contact_groups,id'],
        ]);

        $rows = $this->read($request->file('file'));
        $groups = ContactGroup::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $created = $updated = $skipped = 0;

        DB::transaction(function () use ($rows, $data, $request, $groups, &$created, &$updated, &$skipped) {
            foreach (collect($rows)->unique('email') as $row) {
                if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $skipped++;

                    continue;
                }

                $contact = Contact::query()->where('email', $row['email'])->first();
                $fields = array_filter(array_intersect_key($row, array_flip(['name', 'position', 'organization', 'phone'])), 'filled');

                if ($contact) {
                    if (! ($data['update_existing'] ?? false)) {
                        $skipped++;

                        continue;
                    }
                    $contact->update($fields);
                    $updated++;
                } else {
                    $contact = Contact::create([...$fields, 'name' => $fields['name'] ?? $row['email'], 'email' => $row['email'], 'created_by' => $request->user()->id]);
                    $created++;
                }

                $groupIds = collect(explode(',', $row['groups'] ?? ''))
                    ->map(fn ($g) => mb_strtolower(trim($g)))->filter()
                    ->map(function (string $name) use ($groups) {
                        return $groups[$name] ??= ContactGroup::create(['name' => mb_convert_case($name, MB_CASE_TITLE), 'color' => 'slate'])->id;
                    })
                    ->push($data['group_id'] ?? null)->filter()->unique()->all();

                if ($groupIds !== []) {
                    $contact->groups()->syncWithoutDetaching($groupIds);
                }
            }
        });

        activity('audit')->causedBy($request->user())->withProperties(compact('created', 'updated', 'skipped'))->log('Impor kontak');
        $this->toast("Impor selesai: $created baru, $updated diperbarui, $skipped dilewati.");

        return to_route('contacts.index');
    }

    /** @return list<array<string, string>> */
    private function read(UploadedFile $file): array
    {
        $type = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $reader = SimpleExcelReader::create($file->getRealPath(), $type)->trimHeaderRow();

        if ($type === 'csv') {
            // Ekspor Excel berbahasa Indonesia sering memakai titik koma.
            $handle = fopen($file->getRealPath(), 'r');
            $first = $handle ? (string) fgets($handle) : '';
            $handle && fclose($handle);
            $reader->useDelimiter(substr_count($first, ';') > substr_count($first, ',') ? ';' : ',');
        }

        $rows = [];

        foreach ($reader->getRows() as $raw) {
            $normalized = [];

            foreach ($raw as $header => $value) {
                $key = mb_strtolower(trim((string) $header));

                foreach (self::COLUMNS as $field => $aliases) {
                    if (in_array($key, $aliases, true)) {
                        $normalized[$field] = trim((string) $value);
                    }
                }
            }

            $normalized['email'] = mb_strtolower($normalized['email'] ?? '');

            if ($normalized['email'] !== '' || filled($normalized['name'] ?? null)) {
                $rows[] = $normalized;
            }

            if (count($rows) >= 5000) {
                break;
            }
        }

        return $rows;
    }
}
