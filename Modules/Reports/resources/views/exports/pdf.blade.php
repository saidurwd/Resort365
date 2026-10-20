<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        th { background: #eee; }
        td.n { text-align: right; }
        tr.total td { font-weight: bold; background: #f6f6f6; }
        .figures td { border: 0; padding: 1px 8px 1px 0; }
        .note { margin-top: 10px; color: #555; }
    </style>
</head>
<body>
    <h1>{{ $table->title }}</h1>
    <div class="sub">{{ $subtitle }}</div>
    @if ($table->summary !== [])
        <table class="figures" style="width: auto; margin-bottom: 8px;">
            @foreach ($table->summary as $figure)
                <tr><td>{{ $figure['label'] }}</td><td><strong>{{ $figure['value'] }}</strong></td></tr>
            @endforeach
        </table>
    @endif
    <table>
        <thead><tr>@foreach ($table->columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($table->rows as $row)
                <tr>@foreach ($row as $i => $cell)<td class="{{ $i > 0 ? 'n' : '' }}">{{ $cell }}</td>@endforeach</tr>
            @endforeach
            @if ($table->totals !== null && $table->rows !== [])
                <tr class="total">@foreach ($table->totals as $i => $cell)<td class="{{ $i > 0 ? 'n' : '' }}">{{ $cell }}</td>@endforeach</tr>
            @endif
        </tbody>
    </table>
    @if ($table->note)
        <div class="note">{{ $table->note }}</div>
    @endif
</body>
</html>
