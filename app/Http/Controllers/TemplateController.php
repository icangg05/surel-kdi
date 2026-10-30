<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Support\MailHtml;
use App\Support\MergeFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('templates/Index', [
            'templates' => Template::query()->with('author:id,name')->latest('updated_at')->get()->map(fn (Template $t) => [
                ...$t->only('id', 'name', 'subject'),
                'snippet' => MailHtml::snippet(MailHtml::toText($t->body_html), 140),
                'author' => $t->author?->name,
                'updated_at' => $t->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('templates/Form', ['template' => null, 'mergeFields' => MergeFields::available()]);
    }

    public function edit(Template $template): Response
    {
        return Inertia::render('templates/Form', [
            'template' => $template->only('id', 'name', 'subject', 'body_html'),
            'mergeFields' => MergeFields::available(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Template::create([...$this->validated($request), 'created_by' => $request->user()->id]);
        $this->toast('Templat disimpan.');

        return to_route('templates.index');
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $template->update($this->validated($request));
        $this->toast('Templat diperbarui.');

        return to_route('templates.index');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();
        $this->toast('Templat dihapus.');

        return to_route('templates.index');
    }

    /** @return array<string, string> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:250'],
            'body_html' => ['required', 'string', 'max:1000000'],
        ], [], ['name' => 'nama templat', 'subject' => 'subjek', 'body_html' => 'isi']);

        return [...$data, 'body_html' => MailHtml::sanitize($data['body_html'])];
    }
}
