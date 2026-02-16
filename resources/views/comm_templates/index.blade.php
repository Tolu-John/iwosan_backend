<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>WhatsApp Templates</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; }
        input[type=text] { width: 100%; padding: 6px; }
        .row { margin-bottom: 12px; }
        .actions { display: flex; gap: 8px; }
    </style>
</head>
<body>
    <h1>WhatsApp Templates</h1>

    <form method="POST" action="/admin/comm/templates">
        @csrf
        <div class="row">
            <label>Name</label>
            <input type="text" name="name" required />
        </div>
        <div class="row">
            <label>Language</label>
            <input type="text" name="language" value="en" />
        </div>
        <div class="row">
            <label>Variables (comma-separated)</label>
            <input type="text" name="variables" placeholder="patient_name, appointment_time" />
        </div>
        <div class="row">
            <label><input type="checkbox" name="active" checked /> Active</label>
        </div>
        <button type="submit">Add Template</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Language</th>
                <th>Variables</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($templates as $template)
                <tr>
                    <td>{{ $template->id }}</td>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->language }}</td>
                    <td>{{ implode(', ', $template->variables ?? []) }}</td>
                    <td>{{ $template->active ? 'yes' : 'no' }}</td>
                    <td class="actions">
                        <form method="POST" action="/admin/comm/templates/{{ $template->id }}">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="name" value="{{ $template->name }}" />
                            <input type="text" name="language" value="{{ $template->language }}" />
                            <input type="text" name="variables" value="{{ implode(', ', $template->variables ?? []) }}" />
                            <label><input type="checkbox" name="active" {{ $template->active ? 'checked' : '' }} /> Active</label>
                            <button type="submit">Update</button>
                        </form>
                        <form method="POST" action="/admin/comm/templates/{{ $template->id }}/deactivate">
                            @csrf
                            <button type="submit">Deactivate</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
