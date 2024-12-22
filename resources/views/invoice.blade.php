<!DOCTYPE html>
<!-- saved from url=(0141)http://localhost:63342/bdmsportal_frontend/src/app-admin/admin/billing-module/email-template/invoice-pdf.html?_ijt=cra764s8kimm30uloicapg5kt1 -->
<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>TAX NVOICE</title>
        <link rel="icon" type="image/x-icon" href="http://localhost:63342/bdmsportal_frontend/src/app-admin/admin/billing-module/email-template/img/favicon.ico">
    </head>
    <style>
        @page {margin: 0;}
        .page-break { page-break-after: always; }
    </style>
    <body style="margin: 0">

        <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff">
            <tbody>
                <tr>
                    <td style="border-collapse:collapse; padding: 25px;font-family: 'Roboto', sans-serif;" width="100%" bgcolor="#f7f7f7" valign="top" align="center">

                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #0288d1;">
                            <tbody>
                                <tr>
                                    <td style="border-collapse:collapse;padding-left:20px; color:#ffffff" width="250" height="100" bgcolor="#0288d1" align="left" valign="middle">
                                        <h2 style="font-size: 1.6em; font-weight: 500; margin-bottom: 0; padding: 0;font-family: 'Roboto', sans-serif;">TAX INVOICE</h2>
                                        <p style="font-size: 15px;margin: 0.6rem 0;font-family: 'Roboto', sans-serif;">Inv No: {{$invoice['invoice_no']}}</p>
                                        <p style="font-size: 15px;margin: 0.6rem 0;font-family: 'Roboto', sans-serif;">Date: {{date("d M Y")}}</p>
                                    </td>
                                    <td style="border-collapse:collapse;padding-right:20px" width="250" height="100" bgcolor="#0288d1" align="right" valign="middle">
                                       <?php 
                                       $avatarUrl = "https://client.befree.com.au/assets/images/logo_white.png";
                                       $arrContextOptions=array(
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ),
            );
$type = pathinfo($avatarUrl, PATHINFO_EXTENSION);
$avatarData = file_get_contents($avatarUrl, false, stream_context_create($arrContextOptions));
$avatarBase64Data = base64_encode($avatarData);
$imageData = 'data:image/' . $type . ';base64,' . $avatarBase64Data; ?>
                                        <img src="{{ $imageData }}" alt="" title="LOGO" width="120">
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;">
                            <tbody>
                                <tr>
                                    <td colspan="5">
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;">
                                            <tr>
                                                <td style="border-collapse:collapse;padding-left:20px;vertical-align: top;" width="50%" height="100" bgcolor="#ffffff" align="left" valign="middle">
                                                    <h3 style="margin: 10px 0;font-size: 16px;font-family: 'Roboto', sans-serif;">To</h3>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">{{$billingDetail['billing_name']}}</p>
                                                    
                                                    
                                                <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">
                                                            Attention: {{htmlentities($billingDetail['contact_person'], ENT_QUOTES)}},<br/> {!! nl2br(htmlentities($billingDetail['address'], ENT_QUOTES)) !!}</p>
                                                
                                                </td>
                                                <td style="border-collapse:collapse;padding-right:20px" width="50%" height="100" bgcolor="#ffffff" align="right" valign="middle">
                                                    <h3 style="margin: 10px 0;font-size: 16px;font-family: 'Roboto', sans-serif;">From</h3>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">Befree Pty Ltd</p>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">Suite 3, Level 6</p>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">80 George Street</p>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">Parramatta</p>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">NSW 2150, Australia</p>
                                                    <p style="font-size: 14px;margin: 0.1rem 0; color: #515151;font-family: 'Roboto', sans-serif;">ABN: 20 120 830 784</p>
                                                </td>
                                                </tr>
                                        </table>
                                    </td>
                                <tr>
                                    <td colspan="5">
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;padding: 20px 20px 20px 20px">
                                            <thead>
                                                <tr>
                                                    <th width="88%" style="padding: 0.3rem !important;background-color: #e6e6e6; font-size: 13px;color: #515151;text-align: left;font-family: 'Roboto', sans-serif;">
                                                        Description
                                                    </th>
                                                    <th width="12%" style="padding: 0.2rem !important;background-color: #e6e6e6; font-size: 13px;color: #515151;text-align: right;font-family: 'Roboto', sans-serif;">
                                                        Amount ($) Ex - GST
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($invoiceDescription as $row)                  
                                                <tr>
                                                    <td style="padding: 0.3rem; font-size: 12px; font-weight: 400; color: #515151;border-bottom: 1px solid #f7f7f7;font-family: 'Roboto', sans-serif;">{{$row['description']}}
                                                    </td>
                                                    <td style="padding: 0.3rem; font-size: 12px; font-weight: 400; color: #000000;border-bottom: 1px solid #f7f7f7;text-align: right;font-family: 'Roboto', sans-serif;">{{($row['amount'] != '0.00') ? $row['amount']:''}}</td>
                                                </tr>            
                                                @endforeach
                                                </tbody>
                                        </table>
                                           

                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5">
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;padding: 20px">
                                            <tbody><tr>
                                                    <td style="border-top: 2px solid #f7f7f7;">
                                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background: #ffffff;padding-top: 15px">
                                                            <tbody>
                                                                <tr>
                                                                    <td colspan="3" style="vertical-align:top;font-family: 'Roboto', sans-serif;"><table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                                            @if($billingDetail->payment_id == 3)
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;">BANK DETAILS</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;">CREDIT CARD</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;">DIRECT DEBIT</td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Befree Pty Ltd</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Please call us</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Contact Us to</td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">BSB# 062 223</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">to give your</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Organise</td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Account# 10764309</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Credit card details</td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;">Direct Debit Facility</td>

                                                                            </tr>   
                                                                            @else
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #0288d1;"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>

                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>
                                                                                <td style="padding: 0.2rem; font-size: 12px; color: #515151;"></td>

                                                                            </tr>  
                                                                            @endif
                                                                            <tr>                        
                                                                                <td colspan="3" style="font-size: 12px; color: #515151;vertical-align: bottom;padding-top: 20px"><p style="margin: 0.1rem 0.2rem;color: #000000;">{{$billingDetail->debitLine}}</p>
                                                                                    <p style="margin: 0.2rem 0.2rem;">A surcharge of
                                                                                        25% + GST will be applied to all invoices referred for collection</p>
                                                                                </td>
                                                                            </tr>

                                                                        </table></td>
                                                                    <td colspan="2" style="vertical-align:top;font-family: 'Roboto', sans-serif;">
                                                                        <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
                                                                                <td style="padding: 0; font-size: 13px; color: #515151;text-align: right">Total Ex. GST :
                                                                                </td>
                                                                                <td style="padding:0 0.3rem 0.3rem 0; font-size: 13px; color: #000000;text-align: right;border-bottom: 1px solid #f7f7f7">{{$invoice['net_amount']}}</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #515151;text-align: right;">GST 10% :</td>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #000000;text-align: right;border-bottom: 1px solid #f7f7f7">{{$invoice['gst_amount']}}</td>
                                                                            </tr>
                                                                            <tr> <td style="padding: 0.3rem; font-size: 13px; color: #515151;text-align: right;">Total Inc GST :
                                                                                </td>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #000000;text-align: right;border-bottom: 1px solid #f7f7f7">{{$invoice['paid_amount']}}</td></tr>
                                                                            <tr>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #515151;text-align: right;">Amt. Applied :</td>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #000000;text-align: right;border-bottom: 1px solid #f7f7f7">{{$amountApplied}}</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #000000;text-align: right; font-weight: 500; text-decoration: none; padding-top: 20px;">
                                                                                    Balance Due :
                                                                                </td>
                                                                                <td style="padding: 0.3rem; font-size: 13px; color: #0288d1;text-align: right; font-weight: 600; padding-top: 20px;">
                                                                                    {{number_format((float)$invoice['paid_amount']- $amountApplied,2,'.','')}}
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                    </td>
                                                                </tr>

                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody></table>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5">
                                        <table style="background: #f8f8f8;padding: 20px 5px 0 5px;text-align: center;" width="100%" border="0" cellpadding="0" cellspacing="0">
                                            <tbody><tr>
                                                    <td style="color: #03A9F4; padding-bottom: 5px;font-family: 'Roboto', sans-serif;">Are you not on FIXED Monthly Bookkeeping Fees?</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 14px; color: #515151;font-family: 'Roboto', sans-serif;">Befree offers comprehensive &amp; permanent bookkeeping
                                                        solutions and we can offer Fixed Monthly Bookkeeping fees for your business. Please email
                                                        billing@befree.com.au for a proposal!
                                                    </td>
                                                </tr>
                                            </tbody></table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>


                    </td>
                </tr>
            </tbody>
        </table>



    </body></html>