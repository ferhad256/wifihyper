<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions Report</title>
    <style>
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .print-instructions {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .print-instructions h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .print-instructions ul {
            margin: 0;
            padding-left: 20px;
        }
        .print-instructions li {
            margin: 5px 0;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 20px;
        }
        .header p {
            margin: 3px 0;
            color: #666;
            font-size: 10px;
        }
        .summary {
            margin-bottom: 20px;
            padding: 8px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }
        .summary h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 12px;
        }
        .summary-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 6px;
            background: white;
            border: 1px solid #ddd;
            margin: 0 2px;
        }
        .summary-item h4 {
            margin: 0;
            color: #666;
            font-size: 9px;
        }
        .summary-item p {
            margin: 2px 0 0 0;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 9px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-completed {
            color: #28a745;
            font-weight: bold;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }
        .voucher-code {
            font-family: monospace;
            background-color: #e9ecef;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 8px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
        .transaction-id {
            font-family: monospace;
            font-size: 8px;
        }
        .amount {
            font-weight: bold;
            color: #28a745;
        }
        .fee {
            font-weight: bold;
            color: #ffc107;
        }
        .net-amount {
            font-weight: bold;
            color: #17a2b8;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="print-instructions no-print">
        <h3>📄 How to Save as PDF:</h3>
        <ul>
            <li><strong>Chrome/Edge:</strong> Press Ctrl+P, select "Save as PDF" as destination, then click Save</li>
            <li><strong>Firefox:</strong> Press Ctrl+P, select "Save to File" as destination, then click Save</li>
            <li><strong>Safari:</strong> Press Cmd+P, select "Save as PDF" as destination, then click Save</li>
        </ul>
        <p><strong>Tip:</strong> For best results, use "A4" paper size and "Portrait" orientation.</p>
    </div>

    <div class="header">
        <h1>Transactions Report</h1>
        <p><strong>Tenant:</strong> {{ $tenant->name }}</p>
        <p><strong>Generated:</strong> {{ now()->format('F d, Y \a\t H:i:s') }}</p>
    </div>

    <div class="summary">
        <h3>Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <h4>Gross Revenue</h4>
                <p>UGX {{ number_format($tenant->total_sales) }}</p>
            </div>
            <div class="summary-item">
                <h4>Net Revenue</h4>
                <p>UGX {{ number_format($tenant->net_sales) }}</p>
            </div>
            <div class="summary-item">
                <h4>Total Fees</h4>
                <p>UGX {{ number_format($tenant->total_fees) }}</p>
            </div>
            <div class="summary-item">
                <h4>Completed</h4>
                <p>{{ $transactions->where('status', 'completed')->count() }}</p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Transaction ID</th>
                <th>Hotspot</th>
                <th>Package</th>
                <th>Voucher Sold</th>
                <th>Amount</th>
                <th>Fee</th>
                <th>Net Amount</th>
                <th>Phone Number</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $transaction)
            <tr>
                <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                <td class="transaction-id">{{ $transaction->transaction_id }}</td>
                <td>{{ $transaction->hotspot->name ?? 'N/A' }}</td>
                <td>{{ $transaction->package->name ?? 'N/A' }}</td>
                <td>
                    @if($transaction->voucher)
                        <span class="voucher-code">{{ $transaction->voucher->code }}</span>
                    @else
                        <span style="color: #999;">N/A</span>
                    @endif
                </td>
                <td class="amount">UGX {{ number_format($transaction->amount) }}</td>
                <td class="fee">UGX {{ number_format($transaction->transaction_fee) }}</td>
                <td class="net-amount">UGX {{ number_format($transaction->net_amount) }}</td>
                <td>{{ $transaction->phone_number ?? 'N/A' }}</td>
                <td class="status-{{ $transaction->status }}">{{ ucfirst($transaction->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by WIFIHYPER System | Page 1 of 1</p>
        <p>Total Transactions: {{ $transactions->count() }}</p>
    </div>
</body>
</html> 