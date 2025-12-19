<!-- resources/views/invoices/credit-note.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family:"terminus";
            font-size: 14px;
            margin: 0;
            padding: 0;
        }
        .text-right{
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow:wrap;
        }
        th, td {
            padding-top: 2mm;
            padding-bottom:2mm;
            text-align: left;
            border-left: 0;
            border-right: 0;
        }
        
        .border-b{
            border-bottom: 1px solid #333;
        }
        .pb-1{
            padding-bottom: 1mm;
        }
        .1{
            padding-bottom: 2mm;
        }
        .pt-0{
            padding-top: 0;
        }
        .pb-0{
            padding-bottom: 0;
        }
        .font-bold{
            font-weight: bold;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 3mm;
        }
        .invoice-header h1{
            font-size: 16px;
            margin-top: 3mm;
        }
        .invoice-header p {
            font-size: 14px;
            margin: 0;
        }
        .invoice-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 10mm;
        }
        .text-center{
            text-align: center
        }
        .border-b-d{
            border-bottom: 1px dashed #999;
        }
        h2{
            padding-top:0.5mm; 
            text-align:center; 
            padding-bottom:0mm;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
        }
        .p-x-1{
            padding-left: 1mm;
            padding-right:1mm;
        }
        .p-x-2{
            padding-left: 2mm;
            padding-right:2mm;
        }
        table th{
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
        }
        .mt-0{
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <span style="margin-top: 2mm">&nbsp;&nbsp;</span>
        <h1 class="text-center" style="margin-bottom:0; font-family: 'Courier New', Courier, monospace">
            {{ config('settings.company_name', $site_name) }}
        </h1>
        @if(config('settings.company_address'))
            <p style="font-size: 12px;">{{ config('settings.company_address') }}</p>
        @else
            <p style="font-size: 12px;">{{$site_description}}</p>
        @endif
        @if(config('settings.tax_number'))
            <p style="font-size: 12px;">{{ config('settings.tax_name', 'GST') }}: {{ config('settings.tax_number') }}</p>
        @endif
        <h2>CREDIT NOTE</h2>
        <p>Credit Note Number: #{{ str_pad($creditNoteNumber, 6, '0', STR_PAD_LEFT) }}</p>
        <p>Date: {{ $date }}</p>
        <p>Time: {{ $time }}</p>
        <p>Original Invoice: #{{ str_pad($originalInvoiceNumber, 6, '0', STR_PAD_LEFT) }}</p>
        <p>Refund Method: {{ ucfirst($refundMethod) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="text-center">Rate</th>
                <th class="text-center p-x-1">QTY</th>
                <th class="text-center">Reason</th>
                <th class="text-right">Refund Amount</th>
            </tr>
            <tr>
                <th class="border-b-d" colspan="5" style="height: 0; padding:0;"></td>
            </tr>
        </thead>
        <tbody>
            @php
                $i = 0;
                $total_refund = 0;
            @endphp
            @foreach($items as $item)
                @php $i++; @endphp
                <tr>
                    <td  class="pb-1" style="" colspan="5">{{ $i.'.'.$item->product_name}}</td>
                </tr>
                <tr>
                    <td class="pt-0">{{ $item->product_sku }}</td>
                    <td class="text-center pt-0">{{ number_format($item->original_price, 2, '.', '')  }}</td>
                    <td class="text-center pt-0">{{ $item->quantity }}</td>
                    <td class="text-center pt-0">{{ $item->reason ?? $returnReason }}</td>
                    @php
                        $total_refund += $item->refund_amount;
                    @endphp
                    <td class="text-right pt-0" style="">{{ number_format($item->refund_amount, 2, '.', '')  }}</td>
                </tr>
                <tr>
                    <td class="border-b-d" colspan="5" style="height: 0; padding:0;"></td>
                </tr>
            @endforeach

            <tr>
                <td class="text-center " colspan="5" style="padding-top:3mm; padding-bottom:0; font-size:16px">
                    CREDIT NOTE SUMMARY
                </td>
            </tr>

            <tr>
                <td class="mt-2" colspan="4" style="font-size:16px;">
                    Total Refund Amount
                </td>
                <td class="pt-0" style="text-align: right; font-size:16px">
                    {{ number_format($total_refund, 2) }}
                </td>
            </tr>
            
            <tr>
                <td class="border-b-d" colspan="5" style="height: 0; padding:0; padding-top:2mm"></td>
            </tr>
        </tbody>
    </table>

    @if($notes)
    <div style="margin-top: 5mm;">
        <p style="font-weight: bold;">Notes:</p>
        <p>{{ $notes }}</p>
    </div>
    @endif

    <div class="invoice-footer">
        <p>This credit note has been issued for the returned items.</p>
        <p>Thank you for your understanding!</p>
    </div>
</body>
</html>