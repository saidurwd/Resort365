{{-- Dompdf renders these documents: simple tables and inline CSS only (no Bootstrap, no flexbox). --}}
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    h2 { font-size: 13px; margin: 18px 0 6px; }
    .muted { color: #6c757d; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .grid td { padding: 5px 6px; border-bottom: 1px solid #dee2e6; }
    .grid th { padding: 5px 6px; border-bottom: 2px solid #adb5bd; text-align: left; font-size: 10px; text-transform: uppercase; color: #6c757d; }
    .right { text-align: right; }
    .total td { font-weight: bold; font-size: 13px; }
    .box { border: 1px solid #dee2e6; padding: 8px 10px; margin-top: 12px; }
    .footer { margin-top: 24px; font-size: 9px; }
    .doc-title { font-size: 14px; }
</style>
