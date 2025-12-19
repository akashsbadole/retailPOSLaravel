<?php

namespace App\Http\Controllers;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use App\Models\Order;
use App\Models\SalesReturn;
use Mpdf;
use Illuminate\Http\Request;

class UtilityController extends Controller
{

    public function __construct()
    {
         
        $this->middleware('auth');
    }


    public function print($order_id)
    {   

        $order = Order::with(['items', 'salesReturns'])->findOrFail( $order_id );
        $currency_symbol = config('settings.currency_symbol');
        $site_name = config('settings.site_name');
        $site_description = config('settings.site_description');

        $data = [
            'invoiceNumber' => $order->id,
            'date' => $order->created_at->format('M d, Y'),
            'time' => $order->created_at->format('h:i:s A'),
            'items' => $order->items,
            'order' => $order,
            'currency_symbol' => $currency_symbol,
            'site_name' => $site_name,
            'site_description' => $site_description
        ];

        $html = view('invoices.3-invoice', $data)->render();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        
        $mpdf = new Mpdf\Mpdf([
            'fontDir' => array_merge($fontDirs, [
                public_path(''),
            ]),
            'fontdata' => $fontData + [  
                'terminus' => [
                    'R' => 'Terminus.ttf',
                ]
            ],
            'default_font' => 'terminus',
            'mode' => 'utf-8',
            'shrink_tables_to_fit' => 0,
            'format' => [75, 200],  
            'orientation' => 'P',
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 3,
            'margin_bottom' => 3,
        ]);

        $mpdf->WriteHTML($html);
        return $mpdf->Output('invoice-'.$order_id.'.pdf', 'I');
    }

    public function printCreditNote($return_id)
    {
        $salesReturn = SalesReturn::with(['items.product', 'order'])->findOrFail($return_id);
        $currency_symbol = config('settings.currency_symbol');
        $site_name = config('settings.site_name');
        $site_description = config('settings.site_description');

        $data = [
            'creditNoteNumber' => 'CN' . $salesReturn->id,
            'originalInvoiceNumber' => $salesReturn->order->id,
            'date' => $salesReturn->return_date->format('M d, Y'),
            'time' => $salesReturn->created_at->format('h:i:s A'),
            'items' => $salesReturn->items->map(function ($item) {
                return [
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'original_price' => $item->original_price,
                    'refund_amount' => $item->refund_amount,
                    'reason' => $item->reason,
                ];
            }),
            'refundMethod' => $salesReturn->formatted_refund_method,
            'returnReason' => $salesReturn->formatted_reason,
            'notes' => $salesReturn->notes,
            'currency_symbol' => $currency_symbol,
            'site_name' => $site_name,
            'site_description' => $site_description
        ];

        $html = view('invoices.credit-note', $data)->render();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        
        $mpdf = new Mpdf\Mpdf([
            'fontDir' => array_merge($fontDirs, [
                public_path(''),
            ]),
            'fontdata' => $fontData + [
                'terminus' => [
                    'R' => 'Terminus.ttf',
                ]
            ],
            'default_font' => 'terminus',
            'mode' => 'utf-8',
            'shrink_tables_to_fit' => 0,
            'format' => [75, 200],
            'orientation' => 'P',
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 3,
            'margin_bottom' => 3,
        ]);

        $mpdf->WriteHTML($html);
        return $mpdf->Output('credit-note-'.$return_id.'.pdf', 'I');
    }
}
