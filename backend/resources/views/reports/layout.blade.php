<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · GIAS</title>
    <style>
        @page { margin: 26mm 14mm 22mm 14mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8.5pt; color: #1d2125; }

        header { position: fixed; top: -20mm; left: 0; right: 0; height: 18mm; border-bottom: 1.5pt solid #006B2B; }
        header .college { font-size: 11pt; font-weight: bold; color: #006B2B; }
        header .system { font-size: 8pt; color: #555; }
        header .meta { position: absolute; right: 0; top: 0; text-align: right; font-size: 7.5pt; color: #555; line-height: 1.45; }

        footer { position: fixed; bottom: -15mm; left: 0; right: 0; height: 10mm; font-size: 7.5pt; color: #666; border-top: 0.5pt solid #C9A54E; padding-top: 2mm; }

        h1 { font-size: 14pt; margin: 0 0 1mm; color: #1d2125; }
        .subtitle { font-size: 8.5pt; color: #444; margin-bottom: 4mm; }
        h2 { font-size: 10pt; margin: 5mm 0 2mm; color: #006B2B; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #E4EFE7; color: #0B3D1A; text-align: left; font-size: 7pt; padding: 1.6mm 1.4mm; border-bottom: 0.8pt solid #006B2B; }
        td { padding: 1.1mm 1.4mm; word-wrap: break-word; border-bottom: 0.4pt solid #ddd; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f7f7f5; }
        .num { text-align: right; white-space: nowrap; }
        .best { font-weight: bold; color: #006B2B; }
        .muted { color: #777; }
        .note { font-size: 7.5pt; color: #555; margin-top: 2mm; }

        .signature { margin-top: 12mm; page-break-inside: avoid; }
        .signature .line { margin-top: 8mm; }
    </style>
</head>
<body>
    <header>
        <div class="college">Davao del Norte State College</div>
        <div class="system">GIS-Enabled Integrated Analytics System</div>
        <div class="meta">
            {{ $term }}<br>
            {{ $filters }}<br>
            Generated {{ $generated }} by {{ $generatedBy }}
        </div>
    </header>

    <footer>
        GIAS · {{ $title }} · {{ $term }}
    </footer>

    <main>
        <h1>{{ $title }}</h1>
        <div class="subtitle">{{ $term }} · {{ $filters }}</div>

        @yield('content')

        <div class="signature">
            <div class="line">Prepared by: ______________________________</div>
            <div class="muted" style="margin-top: 1mm; margin-left: 22mm;">Signature over printed name / Date</div>
        </div>
    </main>
</body>
</html>
