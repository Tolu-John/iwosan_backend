<?php

namespace App\Http\Controllers;

use App\Models\CommTemplate;
use Illuminate\Http\Request;

class CommTemplateWebController extends Controller
{
    public function index()
    {
        $templates = CommTemplate::orderBy('id', 'desc')->get();
        return view('comm_templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'language' => 'nullable|string',
            'variables' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        CommTemplate::create([
            'provider' => 'whatsapp',
            'name' => $data['name'],
            'language' => $data['language'] ?? 'en',
            'variables' => $this->parseVars($data['variables'] ?? ''),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $template = CommTemplate::findOrFail($id);
        $data = $request->validate([
            'name' => 'nullable|string',
            'language' => 'nullable|string',
            'variables' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        $template->fill([
            'name' => $data['name'] ?? $template->name,
            'language' => $data['language'] ?? $template->language,
            'variables' => $this->parseVars($data['variables'] ?? ''),
            'active' => $request->boolean('active', $template->active),
        ]);
        $template->save();

        return redirect()->back();
    }

    public function deactivate($id)
    {
        $template = CommTemplate::findOrFail($id);
        $template->active = false;
        $template->save();

        return redirect()->back();
    }

    private function parseVars(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        return array_values($parts);
    }
}
