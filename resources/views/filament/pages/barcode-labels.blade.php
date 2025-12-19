<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Barcode Labels</title>
    <style>
        @switch($labelSize)
            @case('small')
                .label {
                    width: 100px;
                    height: 50px;
                    page-break-inside: avoid;
                    margin: 2px;
                    border: 1px solid #ddd;
                    padding: 4px;
                    font-size: 8px;
                }
                .barcode-container {
                    height: 25px;
                }
                .product-name {
                    font-size: 7px;
                    font-weight: bold;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .price {
                    font-size: 7px;
                    font-weight: bold;
                }
            @case('standard')
                .label {
                    width: 150px;
                    height: 75px;
                    page-break-inside: avoid;
                    margin: 3px;
                    border: 1px solid #ddd;
                    padding: 6px;
                    font-size: 10px;
                }
                .barcode-container {
                    height: 35px;
                }
                .product-name {
                    font-size: 9px;
                    font-weight: bold;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    margin-bottom: 2px;
                }
                .price {
                    font-size: 9px;
                    font-weight: bold;
                }
            @case('large')
                .label {
                    width: 200px;
                    height: 100px;
                    page-break-inside: avoid;
                    margin: 4px;
                    border: 1px solid #ddd;
                    padding: 8px;
                    font-size: 12px;
                }
                .barcode-container {
                    height: 45px;
                }
                .product-name {
                    font-size: 11px;
                    font-weight: bold;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    margin-bottom: 3px;
                }
                .price {
                    font-size: 11px;
                    font-weight: bold;
                }
        @endswitch

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .labels-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .label {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .barcode-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2px 0;
        }

        .barcode-container img {
            max-width: 100%;
            max-height: 100%;
        }

        .product-info {
            text-align: center;
        }

        .barcode-text {
            font-size: 8px;
            color: #666;
            margin-top: 1px;
        }

        @media print {
            .label {
                border: 1px solid #000;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="labels-container">
        @foreach($labels as $label)
            <div class="label">
                <div class="product-info">
                    <div class="product-name">{{ $label['product_name'] }}</div>
                </div>
                
                <div class="barcode-container">
                    {!! $label['barcode_html'] !!}
                </div>
                
                <div class="product-info">
                    <div class="barcode-text">{{ $label['barcode'] }}</div>
                    <div class="price">${{ $label['price'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>