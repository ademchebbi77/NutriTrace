@php($unit = $transport->unit ?? $transport->lot->unit)
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('transfers.consignment.title') }} {{ $transport->consignment_number }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font: 11pt/1.45 "Segoe UI", Arial, sans-serif; color: #1d2733; margin: 0 auto; max-width: 180mm; padding: 10mm 0; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2e7d32; padding-bottom: 4mm; }
        h1 { margin: 0; font-size: 18pt; color: #1b5e20; }
        .muted { color: #5b6673; font-size: 9.5pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        th, td { border: 1px solid #d5dbe1; padding: 2.5mm 3mm; text-align: left; vertical-align: top; }
        th { width: 38%; background: #f4f6f8; }
        .signatures { display: flex; gap: 6mm; margin-top: 10mm; }
        .signatures div { flex: 1; border: 1px solid #d5dbe1; height: 28mm; padding: 2mm 3mm; font-size: 9.5pt; }
        .actions { margin-bottom: 5mm; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">{{ __('transfers.consignment.print') }}</button>
        <a href="{{ area_route('transports.show', $transport) }}">{{ __('ui.back') }}</a>
    </div>

    <header>
        <div>
            <h1>{{ __('transfers.consignment.title') }}</h1>
            <div><strong>{{ __('transfers.consignment.number') }} :</strong> {{ $transport->consignment_number }}</div>
            <div class="muted">NutriTrace · {{ now()->translatedFormat('d F Y') }}</div>
        </div>
        <div style="text-align:center">
            {!! $qr !!}
            <div class="muted" style="max-width:48mm">{{ __('transfers.consignment.scan') }}</div>
        </div>
    </header>

    <table>
        <tr><th>{{ __('transfers.consignment.sender') }}</th><td>{{ $transport->shipper->displayName() }}<br>{{ $transport->origin_label }}</td></tr>
        <tr><th>{{ __('transfers.consignment.recipient') }}</th><td>{{ $transport->recipient->displayName() }}<br>{{ $transport->destination_label }}</td></tr>
        <tr><th>{{ __('transfers.consignment.goods') }}</th><td>{{ $transport->lot->product->name }} — lot {{ $transport->lot->lot_number }}</td></tr>
        <tr><th>{{ __('transfers.consignment.sent') }}</th><td>{{ format_quantity($transport->quantity_transported, $unit) }}</td></tr>
        <tr><th>{{ __('transfers.fields.transport_type') }}</th><td>{{ $transport->transport_type->label() }} · {{ number_format((float) $transport->distance_km, 1, ',', ' ') }} km</td></tr>
        <tr><th>{{ __('transfers.fields.departure_date') }}</th><td>{{ $transport->departure_date->translatedFormat('d F Y H:i') }}</td></tr>
        <tr><th>{{ __('transfers.consignment.handover') }}</th><td>
            @if ($transport->isHandedOver())
                {{ __('transfers.consignment.signed_by', ['name' => $transport->recipient->displayName(), 'date' => $transport->handed_over_at->translatedFormat('d F Y H:i')]) }}
                — {{ __('transfers.consignment.received') }} : {{ format_quantity($transport->handover_quantity, $unit) }}
            @else
                {{ __('transfers.consignment.pending') }}
            @endif
        </td></tr>
    </table>

    <div class="signatures">
        <div>{{ __('transfers.consignment.sender') }}</div>
        <div>{{ __('transfers.consignment.recipient') }}</div>
    </div>
</body>
</html>
