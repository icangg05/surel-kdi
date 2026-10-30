<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    private const SUBJECTS = [
        'App\Models\Contact' => 'Kontak',
        'App\Models\ContactGroup' => 'Grup',
        'App\Models\Mailbox' => 'Mailbox',
        'App\Models\Domain' => 'Domain',
        'App\Models\User' => 'Pengguna',
        'App\Models\Template' => 'Templat',
        'App\Models\Broadcast' => 'Siaran',
    ];

    private const EVENTS = ['created' => 'membuat', 'updated' => 'mengubah', 'deleted' => 'menghapus'];

    public function __invoke(Request $request): Response
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'like', "%$s%"))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'causer' => $a->causer?->name ?? 'Sistem',
                'action' => isset(self::EVENTS[$a->event ?? ''])
                    ? self::EVENTS[$a->event].' '.Str::lower(self::SUBJECTS[$a->subject_type] ?? class_basename((string) $a->subject_type))
                    : $a->description,
                'subject' => $a->subject_type ? (self::SUBJECTS[$a->subject_type] ?? class_basename($a->subject_type)).' #'.$a->subject_id : null,
                'changes' => $a->attribute_changes ?? null,
                'properties' => $a->properties,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/Activity', ['activities' => $activities, 'filters' => ['q' => $request->query('q')]]);
    }
}
