<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $detail['invoice_number'] ?? 'Invoice' }}</title>
    <style>
        @page {
            margin: 2cm 1.5cm 2.5cm 1.5cm;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1f2937;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(239, 68, 68, 0.15);
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
            font-family: 'DejaVu Sans', sans-serif;
        }
        .badge-overdue {
            position: fixed;
            top: 1.5cm;
            right: 1.5cm;
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            z-index: 9998;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 2px solid {{ $template->primary_color ?: '#0F172A' }};
            padding-bottom: 16px;
        }
        .company-info {
            flex: 1;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: {{ $template->primary_color ?: '#0F172A' }};
            margin-bottom: 4px;
        }
        .company-address {
            font-size: 11px;
            color: #4b5563;
            margin-bottom: 2px;
        }
        .company-npwp {
            font-size: 11px;
            color: #4b5563;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin: 0 0 8px 0;
        }
        .invoice-meta {
            font-size: 11px;
            color: #4b5563;
        }
        .invoice-meta div {
            margin-bottom: 4px;
        }
        .invoice-meta .label {
            font-weight: 600;
            display: inline-block;
            width: 120px;
        }
        .section {
            margin-top: 24px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: {{ $template->primary_color ?: '#0F172A' }};
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }
        .outlet-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .outlet-block {
            flex: 1;
        }
        .outlet-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .outlet-value {
            font-size: 11px;
            font-weight: 500;
            color: #1f2937;
        }
        table.line-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.line-items th,
        table.line-items td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
        }
        table.line-items th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        table.line-items td.number {
            text-align: right;
        }
        table.line-items td.center {
            text-align: center;
        }
        .totals {
            margin-top: 16px;
            float: right;
            width: 300px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 6px 10px;
            font-size: 11px;
            border: 1px solid #e5e7eb;
        }
        .totals td.label {
            font-weight: 500;
            background: #f9fafb;
        }
        .totals td.value {
            text-align: right;
        }
        .totals tr.total-row td {
            font-weight: bold;
            background: #f3f4f6;
        }
        .payments {
            margin-top: 24px;
        }
        .payments table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .payments th,
        .payments td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            font-size: 10px;
            text-align: left;
        }
        .payments th {
            background: #f9fafb;
            font-weight: 600;
        }
        .payments td.number {
            text-align: right;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
        .signer {
            margin-top: 24px;
            text-align: right;
        }
        .signer-name {
            font-weight: 600;
        }
        .signer-title {
            font-size: 10px;
            color: #6b7280;
        }
        .notes {
            margin-top: 16px;
            padding: 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 10px;
        }
        .logo {
            max-height: 60px;
            max-width: 150px;
        }
    </style>
</head>
<body>
    @if ($isCancelled)
        <div class="watermark">BATAL</div>
    @endif

    @if ($isOverdue)
        <div class="badge-overdue">OVERDUE</div>
    @endif

    <div class="header">
        <div class="company-info">
            @if ($template->logo_path)
                <img src="{{ asset('storage/'.$template->logo_path) }}" alt="Logo" class="logo">
            @endif
            <div class="company-name">{{ $template->company_name }}</div>
            <div class="company-address">{{ $template->address }}</div>
            @if ($template->show_npwp)
                <div class="company-npwp">NPWP: {{ $template->npwp }}</div>
            @endif
        </div>

        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div class="invoice-meta">
                <div><span class="label">Nomor:</span> {{ $detail['invoice_number'] }}</div>
                <div><span class="label">Tanggal:</span> {{ $detail['issue_date'] }}</div>
                <div><span class="label">Jatuh Tempo:</span> {{ $detail['due_date'] }}</div>
                <div><span class="label">Status:</span> {{ ucfirst($detail['status']) }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Informasi Outlet</div>
        <div class="outlet-info">
            <div class="outlet-block">
                <div class="outlet-label">Nama Outlet</div>
                <div class="outlet-value">{{ $detail['outlet']['name'] ?? '-' }}</div>
            </div>
            <div class="outlet-block">
                <div class="outlet-label">Alamat</div>
                <div class="outlet-value">{{ ($detail['outlet']['address'] ?? '').', '.($detail['outlet']['city'] ?? '') }}</div>
            </div>
            @if ($template->show_outlet_phone && !empty($detail['outlet']['phone']))
            <div class="outlet-block">
                <div class="outlet-label">Telepon</div>
                <div class="outlet-value">{{ $detail['outlet']['phone'] }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Detail Barang</div>
        <table class="line-items">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 45%;">Nama Produk</th>
                    <th style="width: 15%;" class="center">Qty</th>
                    <th style="width: 17%;" class="number">Harga Satuan</th>
                    <th style="width: 18%;" class="number">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail['line_items'] ?? [] as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['product_name_snapshot'] ?? $item['product_name'] ?? '-' }}</td>
                    <td class="center">{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                    <td class="number">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <table>
            <tr>
                <td class="label">Total Tagihan</td>
                <td class="value">Rp {{ number_format($detail['total_amount'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Total Dibayar</td>
                <td class="value">Rp {{ number_format($detail['paid_amount'], 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Sisa Tagihan</td>
                <td class="value">Rp {{ number_format($detail['balance_amount'], 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if (!empty($detail['payments']))
    <div class="section payments">
        <div class="section-title">Riwayat Pembayaran</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Tanggal</th>
                    <th style="width: 15%;">Metode</th>
                    <th style="width: 15%;" class="center">Status</th>
                    <th style="width: 20%;" class="number">Jumlah</th>
                    <th style="width: 40%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail['payments'] as $payment)
                <tr>
                    <td>{{ $payment['created_at'] ? \Carbon\Carbon::parse($payment['created_at'])->format('d/m/Y H:i') : '-' }}</td>
                    <td>{{ ucfirst($payment['payment_method']) }}</td>
                    <td class="center">{{ ucfirst($payment['status']) }}</td>
                    <td class="number">Rp {{ number_format($payment['amount'], 0, ',', '.') }}</td>
                    <td>-</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if (!empty($template->notes))
    <div class="notes">
        <strong>Catatan:</strong> {{ $template->notes }}
    </div>
    @endif

    @if (!empty($template->footer_text))
    <div class="footer">
        {{ $template->footer_text }}
    </div>
    @endif

    @if (!empty($template->signer_name))
    <div class="signer">
        <div class="signer-name">{{ $template->signer_name }}</div>
        @if (!empty($template->signer_title))
            <div class="signer-title">{{ $template->signer_title }}</div>
        @endif
    </div>
    @endif
</body>
</html>