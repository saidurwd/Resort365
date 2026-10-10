<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 15px; margin: 0; } p { margin: 2px 0 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; } th, td { border-bottom: 1px solid #ddd; padding: 3px 5px; text-align: right; }
        th:first-child, td:first-child { text-align: left; } th { background: #f1f1f1; } .bold td { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <p>{{ $report->subtitle }}</p>
    <table>
        <thead><tr>@foreach ($report->columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($report->rows as $row)
                <tr class="{{ ($row['bold'] ?? false) ? 'bold' : '' }}">
                    @foreach ($row['cells'] as $index => $cell)<td>{!! $index === 0 ? str_repeat('&nbsp;&nbsp;', $row['indent'] ?? 0) : '' !!}{{ $cell }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @foreach ($report->notes as $note)<p>{{ $note }}</p>@endforeach
</body>
</html>
