<?php

namespace App\Http\Controllers;

use App\Models\CommTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommTemplateController extends Controller
{
    public function index()
    {
        $templates = CommTemplate::orderBy('id', 'desc')->get();
        return response()->json(['data' => $templates], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'nullable|string',
            'name' => 'required|string',
            'language' => 'nullable|string',
            'variables' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $template = CommTemplate::create([
            'provider' => $data['provider'] ?? 'whatsapp',
            'name' => $data['name'],
            'language' => $data['language'] ?? 'en',
            'variables' => $data['variables'] ?? [],
            'active' => $data['active'] ?? true,
        ]);

        return response()->json(['data' => $template], 200);
    }

    public function update(Request $request, $id)
    {
        $template = CommTemplate::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'provider' => 'nullable|string',
            'name' => 'nullable|string',
            'language' => 'nullable|string',
            'variables' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $template->fill($data);
        $template->save();

        return response()->json(['data' => $template], 200);
    }

    public function destroy($id)
    {
        $template = CommTemplate::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }
        $template->active = false;
        $template->save();

        return response()->json(['message' => 'Template deactivated'], 200);
    }
}
