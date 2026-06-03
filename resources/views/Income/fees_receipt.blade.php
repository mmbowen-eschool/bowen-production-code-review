<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @font-face {
            font-family: 'NotoSansSC';
            src: url("{{ public_path('assets/fonts/NotoSansSC-Regular.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'NotoSansSC';
            src: url("{{ public_path('assets/fonts/NotoSansSC-Bold.ttf') }}") format('truetype');
            font-weight: bold; 
            font-style: normal;
        }

        * {
            font-family: 'NotoSansSC', 'DejaVu Sans', sans-serif;
        }

        th, strong, .title, .label {
            font-family: 'NotoSansSC', 'DejaVu Sans', sans-serif;
            font-weight: bold;
        }

        body {
            font-size: 12px;
            color: #222;
            line-height: 1.45;
        }

        .container {
            padding: 6px 10px;
        }

        hr.end-header {
            border: 0;
            border-top: 1px solid #999;
            margin: 14px 0 18px 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .school-name {
            font-size: 19px;
            font-weight: bold;
        }

        .school-address {
            font-size: 12px;
            color: #666;
        }

        .receipt-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 8px 0 4px 0;
        }

        .receipt-no {
            text-align: center;
            font-size: 12px;
            color: #444;
            margin: 0 0 10px 0;
        }

        .card {
            border: 1px solid #D9D9D9;
            border-radius: 8px;
            background-color: #FAFAFA;
            padding: 8px 10px;
            margin: 10px 0 14px 0;
        }

        .row-table {
            width: 100%;
            border-collapse: collapse;
        }

        .label {
            color: #555;
            width: 120px;
            white-space: nowrap;
        }

        .en-small {
            color: #777;
            font-size: 10px;
            font-weight: normal;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #D9D9D9;
            border-radius: 8px;
        }

        .receipt-table th,
        .receipt-table td {
            border: 1px solid #D9D9D9;
            padding: 8px 10px;
            vertical-align: top;
        }

        .receipt-table thead th {
            background-color: #F7F7F7;
            border-bottom: 1px solid #D9D9D9;
        }

        .amount-col {
            text-align: right;
            white-space: nowrap;
            width: 18%;
        }

        .money-text {
            white-space: nowrap;
            word-break: keep-all;
            letter-spacing: normal;
        }

        .total-row th,
        .total-row td {
            background-color: #F3FBF5;
            font-weight: bold;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Student Fee Receipt || {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
</head>
<body>
<div class="container">
    <table class="header-table">
        <tr>
            <td style="width: 70px;">
                @if ($school['horizontal_logo'] ?? '')
                    <img style="height: 56px; width: 56px;" src="{{ public_path('storage/') . $school['horizontal_logo'] }}" alt="">
                @else
                    <img style="height: 56px; width: 56px;" src="{{ public_path('assets/horizontal-logo2.svg') }}" alt="">
                @endif
            </td>
            <td>
                <div class="school-name">{{ $school['school_name'] ?? '' }}</div>
                <div class="school-address">{{ $school['school_address'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="receipt-title">学生缴费收据 / Student Fee Receipt</div>
    <div class="receipt-no">收据编号 / Receipt No.: {{ $feesPaid->id ?? '' }}</div>

    <hr class="end-header">

    <div class="card">
        <div class="title">学生信息 / Student Details</div>
        <table class="row-table">
            <tr>
                <th class="label text-left">学生姓名 <span class="en-small">/ Name</span></th>
                <td class="text-left">: {{ $student->user->full_name }}</td>
            </tr>
            <tr>
                <th class="label text-left">班级 <span class="en-small">/ Class</span></th>
                <td class="text-left">: {{ $student->class_section->full_name ?? '' }}</td>
            </tr>
        </table>
    </div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width: 10%;">序号 <span class="en-small">/ No.</span></th>
                <th style="width: 36%;">费用项目 <span class="en-small">/ Fee Type</span></th>
                <th style="width: 36%;">付款信息 <span class="en-small">/ Payment Info</span></th>
                <th class="amount-col">金额 <span class="en-small">/ Amount</span></th>
            </tr>
        </thead>
                    @php
                        $no = 1;
                        $total_fees = 0;
                        $total_optional_fees = 0;
                        $due_charges = 0;
                    @endphp
                    <tbody>
                    @php
                        $compulsoryFeesType = $feesPaid->fees->compulsory_fees->pluck('fees_type_name');
                        $compulsoryFeesType = implode(" , ",$compulsoryFeesType->toArray());
                    @endphp
                    {{--Compulsory Fees Listing --}}
                    @if(isset($feesPaid->compulsory_fee) && $feesPaid->compulsory_fee->isNotEmpty())
                        @foreach ($feesPaid->compulsory_fee as $index => $compulsoryFee)
                            @if($compulsoryFee->type == "Full Payment")
                                    <tr>
                                        <td class="text-left">{{ $no++ }}</td>
                                        <td class="text-left">
                                            全额付款 <span class="en-small">/ Full Payment</span><br>
                                            <span class="en-small">({{ $compulsoryFeesType }})</span>
                                        </td>
                                        <td class="text-left">
                                            付款方式 <span class="en-small">/ Payment Mode</span>: {{ $compulsoryFee->mode }}<br>
                                            缴费日期 <span class="en-small">/ Payment Date</span>: {{ date('d-m-Y', strtotime($compulsoryFee->date)) }}
                                        </td>
                                        <td class="amount-col">
                                            <span class="money-text">{{ format_money($compulsoryFee->amount) }}</span>
                                        </td>
                                    </tr>
                                    @if ($index === count($feesPaid->compulsory_fee) - 1 && $compulsoryFee->due_charges)
                                        <tr>
                                            <td class="text-left">{{ $no++ }}</td>
                                            <td class="text-left">
                                                逾期费用 <span class="en-small">/ Due Charges</span>
                                            </td>
                                            <td class="text-left">-</td>
                                            <td class="amount-col">
                                                <span class="money-text">{{ format_money($compulsoryFee->due_charges ?? 0) }}</span>
                                                @php
                                                    $due_charges += $compulsoryFee->due_charges ?? 0;
                                                @endphp
                                            </td>
                                        </tr>
                                    @endif
                            @elseif($compulsoryFee->type == "Installment Payment")
                                <tr>
                                    <td class="text-left">{{ $no++ }}</td>
                                    <td class="text-left">
                                        {{ $compulsoryFee->installment_fee->name }}<br>
                                        <span class="en-small">({{ $compulsoryFeesType }})</span>
                                    </td>
                                    <td class="text-left">
                                        付款方式 <span class="en-small">/ Payment Mode</span>: {{ $compulsoryFee->mode }}<br>
                                        缴费日期 <span class="en-small">/ Payment Date</span>: {{ date('d-m-Y', strtotime($compulsoryFee->date)) }}
                                        @if ((float) $compulsoryFee->due_charges > 0)
                                            <br>
                                            逾期费用 <span class="en-small">/ Due Charges</span>: <span class="money-text">{{ format_money($compulsoryFee->due_charges) }}</span>
                                        @endif
                                        @php
                                            $due_charges += $compulsoryFee->due_charges ?? 0;
                                        @endphp
                                    </td>
                                    <td class="amount-col">
                                        <span class="money-text">{{ format_money($compulsoryFee->amount + $compulsoryFee->due_charges) }}</span>
                                    </td>
                                </tr>
                            @endif

                            @php
                                $total_fees += $compulsoryFee->amount;
                            @endphp

                        @endforeach
                    @endif

                    {{-- Optional Fees Listing --}}
                    @if(isset($feesPaid->optional_fee) && $feesPaid->optional_fee->isNotEmpty())
                        @foreach ($feesPaid->optional_fee as $optionalFee)
                            <tr>
                                <td class="text-left">{{ $no++ }}</td>
                                <td class="text-left">
                                    {{ $optionalFee->fees_class_type->fees_type_name }}
                                    <span class="en-small">({{ __('optional') }})</span>
                                </td>
                                <td class="text-left">
                                    付款方式 <span class="en-small">/ Payment Mode</span>: {{ $optionalFee->mode }}<br>
                                    缴费日期 <span class="en-small">/ Payment Date</span>: {{ date('d-m-Y', strtotime($optionalFee->date)) }}
                                </td>
                                <td class="amount-col"><span class="money-text">{{ format_money($optionalFee->amount) }}</span></td>
                            </tr>
                            @php
                                $total_fees += $optionalFee->amount;
                                $total_optional_fees += $optionalFee->amount;
                            @endphp
                        @endforeach
                    @endif
                    <tr class="total-row">
                        <td></td>
                        <td colspan="2" class="text-left"><strong>合计金额 <span class="en-small">/ Total Amount</span></strong></td>
                        <td class="amount-col"><span class="money-text">{{ format_money($total_fees + $due_charges) }}</span></td>
                    </tr>

                    @if (($feesPaid->fees->total_compulsory_fees + $due_charges) != ($total_fees - $total_optional_fees))
                        <tr>
                            <td></td>
                            <td colspan="2" class="text-left"><strong>必缴费用合计 <span class="en-small">/ Total Compulsory Fees Amount</span></strong></td>
                            <td class="amount-col"><span class="money-text">{{ format_money($feesPaid->fees->total_compulsory_fees + $due_charges) }}</span></td>
                        </tr>

                        <tr>
                            <td></td>
                            <td colspan="2" class="text-left"><strong>剩余费用 <span class="en-small">/ Remaining Fees Amount</span></strong></td>
                            <td class="amount-col"><span class="money-text">{{ format_money($feesPaid->fees->total_compulsory_fees - $total_fees + $total_optional_fees) }}</span></td>
                        </tr>
                    @endif
                    
                    </tbody>
    </table>

</body>

</html>
