<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Invoice;
use Barryvdh\DomPDF\Facade as PDF;
use DB;

class InvoiceSendController extends Controller {

    /**
     * Store invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function sendInvoiceDetail(Request $request, $id) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'invoice_no' => 'required',
            'status_id' => 'required|in:11',
            'entity_id' => 'required'], []);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $checkRight = \App\Models\Backend\UserTabRight::where("user_id", loginUser())->where("tab_id", "88")->where("add_edit", "1")->count();
        if ($checkRight == 0) {
            return createResponse(config('httpResponse.UNPROCESSED'), "You don't have right to send invoice to the client", ['error' => $validator->errors()->first()]);
        }

        $dataInvoice = $this->generatePDFData($id, $request->input("invoice_no"), $request->input("status_id"), $request->input("entity_id"), 0);

        return createResponse(config('httpResponse.SUCCESS'), 'Invoice List data', ['data' => $dataInvoice]);
        /* } catch (\Exception $e) {
          $data['to'] = 'bdmsdeveloper@befree.com.au';
          $data['subject'] = 'Invoice Auto Invoice Send cron not run dated: ' . date('d-m-Y H:i:s');
          $data['content'] = '<h3 style="font-family:sans-serif;">Hello Team,</h3><p style="font-family:sans-serif;">Update remark previous day cron does not execute due to below mentioned exception.</p><p style="font-family:sans-serif;">' . $e->getMessage() . '</p>';
          storeMail('', $data);
          } */
    }

    public function showSendInvoiceDetail(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'invoice_no' => 'required'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $invoiceTemplate = \App\Models\Backend\InvoiceTemplate::getTemplateDetail($request->input('invoice_no'))->get();

            return createResponse(config('httpResponse.SUCCESS'), 'Detail', ["data" => $invoiceTemplate]);
        } catch (\Exception $e) {
            app('log')->error("Invoice Preview failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not preview invoice', ['error' => 'Could not preview invoice']);
        }
    }

    public function downloadPDF(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'invoice_no' => 'required'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $invoiceTemplate = \App\Models\Backend\InvoiceTemplate::getTemplateDetail($request->input('invoice_no'))->first();

            $file = storageEfs() . $invoiceTemplate->attachment_path . $invoiceTemplate->attachment;
            return response()->download($file);
        } catch (\Exception $e) {
            app('log')->error("Invoice download failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not dowmload invoice', ['error' => 'Could not dowmload invoice']);
        }
    }

    /**
     * Store invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'invoice_no' => 'required',
            'to' => 'required|email_array',
            'cc' => 'email_array',
            'bcc' => 'email_array',
            'subject' => 'required',
            'body' => 'required',
            'payment_id' => 'required',
            'reference' => 'required',
            'status_id' => 'required|in:3'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $invoice = Invoice::find($id);
        if (!$invoice) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice Description does not exist', ['error' => 'The Invoice Description does not exist']);
        }

        if ($invoice->staus_id == 3) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice already send to client', ['error' => 'The Invoice already send to client']);
        }
        $amountApplied = $request->has('amount_applied') ? $request->input('amount_applied') : '0';
        //pdf generate
        $pdfName = $this->generatePDFData($id, $invoice->invoice_no, 11, $invoice->entity_id, $amountApplied, 1);
        // ezidebit case
        if ($request->input('payment_id') == 1) {
            $debtor_due_date = strtotime("+19 days", strtotime(date("Y-m-d")));
        }
        // credit card / net transfer case
        else {
            $debtor_due_date = strtotime("+15 days", strtotime(date("Y-m-d")));
        }

        $debtor_due_date = date('Y-m-d', $debtor_due_date);
        $dataArray = [
            'invoice_no' => $request->input('invoice_no'),
            'from_email' => $request->input('from_email'),
            'to' => trim($request->input('to')),
            'cc' => trim($request->input('cc')),
            'bcc' => trim($request->input('bcc')),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'reference' => $request->input('reference'),
            'attachment' => $pdfName['fileName'],
            'attachment_path' => $pdfName['pdfPath'],
            'amount_applied' => $amountApplied,
            'created_on' => date("Y-m-d H:i:s"),
            'created_by' => loginUser()
        ];

        $data['to'] = str_replace(' ', '', $request->input('to'));
        $data['from'] = $request->input('from_email');
        $data['cc'] = str_replace(' ', '', $request->input('cc'));
        $data['bcc'] = str_replace(' ', '', $request->input('bcc'));
        $data['content'] = $request->input('body');
        $data['subject'] = $request->input('subject');
        $data['attachment'] = array($pdfName['pdfPath'] . $pdfName['fileName']);
        //send mail to the client
        storeMail($request, $data);
        //update sent_date ,dm_date,due_date
        Invoice::where("invoice_no", $request->input('invoice_no'))->update(
                ['send_date' => date("Y-m-d H:i:s"),
                    'due_date' => date('Y-m-d', strtotime("+7 days", strtotime(date("Y-m-d")))),
                    'dm_date' => $debtor_due_date,
                    "status_id" => 3]);
        $invoiceTemplate = \App\Models\Backend\InvoiceTemplate::where("invoice_no", $request->input('invoice_no'))->first();
        if (isset($invoiceTemplate->id)) {
            \App\Models\Backend\InvoiceTemplate::where("id", $invoiceTemplate->id)->update($dataArray);
        } else {
            $invoiceTemplate = \App\Models\Backend\InvoiceTemplate::create($dataArray);
        }
        // add log
        $allInvoice = Invoice::where("invoice_no", $invoice->invoice_no)->get();
        foreach ($allInvoice as $invoices) {
            $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoices->id, 3);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Invoice has been sent to the client successfully', ['data' => $invoiceTemplate]);
        /*  } catch (\Exception $e) {
          app('log')->error("Invoice send to the client failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send invoice to the client', ['error' => 'Could not send invoice to the client']);
          } */
    }

    public static function generatePDFData($id, $invoiceNo, $statusId, $entityId, $amountApplied = 0, $PDF = NULL, $type = NULL) {
        //get Invoice  
        //try {
        $invoices = Invoice::select("id", "entity_id", "invoice_no", "to_period", "net_amount", "gst_amount", "paid_amount", "discount_amount", "discount_type", "card_surcharge", "surcharge_amount", "invoice_type", "service_id", "billing_id", "is_fixed_fees", "parent_id")->where("invoice_no", $invoiceNo)->get();
        $billingDetail = \App\Models\Backend\Billing::leftjoin("entity as e", "e.id", "billing_basic.entity_id")
                ->select("e.name", "e.billing_name", "e.trading_name", "e.code", "billing_basic.to_email", "billing_basic.cc_email", "billing_basic.card_id", "billing_basic.surcharge", "billing_basic.address", "billing_basic.contact_person", "billing_basic.payment_id")
                ->where("entity_id", $entityId);

        if ($billingDetail->count() == 0) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice Billing Detail does not exist', ['error' => 'The Invoice Billing Detail does not exist']);
        }
        $billingDetail = $billingDetail->first();
        $i = $netAmount = $gstAmount = $discountAmount = $discountAdvAmount = $surchargeAmount = $paidAmount = 0;
        $addtionalDescription = array();
        $reference = array();
        $card = config("constant.card");
        // add Discount code description for invoice
        $discountAccountCode = array(
            1 => "1",
            2 => "7",
            6 => "14",
            4 => "10",
            5 => "48",
            7 => "60");
        foreach ($invoices as $invoice) {
            if ($invoice->discount_type == 'Fixed' && $invoice->discount_amount > 0) {
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $invoice->id)->where("hide", "3")->delete();
                $discountAmount = $discountAmount + (float) $invoice->discount_amount;
                $addtionalDescription[$i]['invoice_id'] = $invoice->id;
                $addtionalDescription[$i]['description'] = 'Less : Discount on above fees';
                if ($invoice->service_id == 7) {
                    $dicountCode = $discountAccountCode[7];
                } else {
                    $dicountCode = $discountAccountCode[1];
                }
                $addtionalDescription[$i]['amount'] = "-" . $discountAmount;
                $addtionalDescription[$i]['account_id'] = $dicountCode;
                $addtionalDescription[$i]['hide'] = 1;
                $i++;
            } else if ($invoice->discount_type == 'Advance' && $invoice->discount_amount > 0) {
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $invoice->id)->where("hide", "1")->delete();
                $discountAdvAmount = $discountAdvAmount + (float) $invoice->discount_amount;
                $addtionalDescription[$i]['invoice_id'] = $invoice->id;
                $addtionalDescription[$i]['description'] = 'Less : Advance received';
                $addtionalDescription[$i]['amount'] = "-" . $discountAdvAmount;
                $addtionalDescription[$i]['account_id'] = $discountAccountCode[1];
                $addtionalDescription[$i]['hide'] = 3;
                $i++;
            }
            if ($invoice->card_surcharge != '0.00' && $invoice->card_surcharge != '') {
                $surchargeAmount = $surchargeAmount + (float) $invoice->surcharge_amount;
                $addtionalDescription[$i]['invoice_id'] = $invoice->id;
                $addtionalDescription[$i]['description'] = 'Add: ' . $card[$billingDetail->card_id] . ' card surcharge (' . $invoice->card_surcharge . '%)';
                $addtionalDescription[$i]['amount'] = $surchargeAmount;
                $addtionalDescription[$i]['account_id'] = 71;
                $addtionalDescription[$i]['hide'] = 2;
                $i++;
            }

            //$netAmount = $netAmount + (float) $invoice->net_amount + (float) $surchargeAmount;

            if ($invoice->parent_id == 0) {
                $reference[] = self::getReference($invoice);
            }
        }
        $invoiceDetail = Invoice::select("entity_id", "invoice_no", "to_period", DB::raw("SUM(net_amount) as net_amount,SUM(gst_amount) as gst_amount,SUM(paid_amount) as paid_amount,SUM(surcharge_amount) as surcharge_amount"), "card_surcharge", "invoice_type", "service_id", "billing_id", "is_fixed_fees")
                        ->where("invoice_no", $invoiceNo)->first();
        $netAmount = $invoiceDetail->net_amount + $surchargeAmount;
        $gstAmount = (float) ($netAmount * 10) / 100;
        $paidAmount = (float) ($netAmount + $gstAmount);
        $invoiceDetail['net_amount'] = number_format($netAmount, 2, '.', '');
        $invoiceDetail['gst_amount'] = number_format($gstAmount, 2, '.', '');
        $invoiceDetail['paid_amount'] = number_format($paidAmount, 2, '.', '');
        //save additional description
        $invoiceDescription = \App\Models\Backend\InvoiceDescription::where("invoice_no", $invoiceNo)->where("hide", "0");

        foreach ($addtionalDescription as $row) {
            $Description = \App\Models\Backend\InvoiceDescription::where("invoice_no", $invoiceNo)->where("hide", $row['hide'])->first();

            $countDes = \App\Models\Backend\InvoiceDescription::where("invoice_id", $row['account_id'])->where("hide", $row['hide'])->count();

            $descriptionData = [
                'invoice_id' => $row['invoice_id'],
                'invoice_no' => $invoiceNo,
                'inv_account_id' => $row['account_id'],
                'description' => $row['description'],
                'amount' => $row['amount'],
                'hide' => $row['hide'],
                'sort_order' => $invoiceDescription->count() + 1,
                'created_on' => date("Y-m-d:h:i:s"),
                'created_by' => loginUser()];

            if (isset($Description->id)) {
                \App\Models\Backend\InvoiceDescription::where("id", $Description->id)->update($descriptionData);
            } else if ($countDes == 0) {
                \App\Models\Backend\InvoiceDescription::Insert($descriptionData);
            }
        }
        //get invoice description again with discount and card surcharge

        $invoiceDescription = \App\Models\Backend\InvoiceDescription::leftjoin("invoice as i", "i.id", "invoice_desc.invoice_id")
                        ->where("invoice_desc.invoice_no", $invoiceNo)->where("hide", "!=", "2")->orderBy("i.service_id")->orderby("invoice_desc.sort_order");

        $cardSurchargeInvoiceDescription = \App\Models\Backend\InvoiceDescription::
                where("invoice_desc.invoice_no", $invoiceNo)->where("hide", "=", "2");
        if ($invoiceDescription->count() <= 0) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice Description does not exist', ['error' => 'The Invoice Description does not exist']);
        }
        $largeInvoice = 0;
        $invoiceDescriptionNext = array();
        /* if ($invoiceDescription->count() > 41) {
          $total = $invoiceDescription->count();
          $largeInvoice = 1;
          $invoiceDescription = $invoiceDescription->take(30)->get()->toArray();
          $invoiceDescriptionData = \App\Models\Backend\InvoiceDescription::leftjoin("invoice as i", "i.id", "invoice_desc.invoice_id")
          ->where("invoice_desc.invoice_no", $invoiceNo)->where("hide", "!=", "2")->orderBy("i.service_id")->orderby("invoice_desc.sort_order");

          $invoiceDescriptionData = $invoiceDescriptionData->skip(31)->take($total)->get();
          $invoiceDescriptionNext = array();
          foreach ($invoiceDescriptionData as $innext) {
          $invoiceDescriptionNext[] = $innext;
          }
          if ($cardSurchargeInvoiceDescription->count() > 0) {
          $invoiceDescriptionNext = array_merge($invoiceDescriptionNext, $cardSurchargeInvoiceDescription->get()->toArray());
          }
          } else { */
        $invoiceDescription = $invoiceDescription->get()->toArray();
        if ($cardSurchargeInvoiceDescription->count() > 0) {
            $invoiceDescription = array_merge($invoiceDescription, $cardSurchargeInvoiceDescription->get()->toArray());
        }
        //}

        $format = 'd M Y';
        $curr_date = date($format);
        $debit_date = date($format, strtotime($curr_date . ' +7 days'));

        if ($billingDetail->payment_id == '1' || $billingDetail->payment_id == '2') {
            $debit_line = "Your nominated account will be direct debited in 5 business days.";
        }
        // net transfer / credit card
        else if ($billingDetail->payment_id == '3') {
            $debit_line = "Please make payment within 5 business days.";
        }
        $payemntDes = ($billingDetail->payment_id == '1' || $billingDetail->payment_id == '2') ? "will be debited" : "is due";
        $subject = "Invoice " . $invoiceNo . " from BE FREE PTY. LTD. for " . $billingDetail->billing_name;
        $body = "Hi " . $billingDetail->contact_person . ",<br/><br/>
        Here's invoice " . $invoiceNo . " for " . number_format((float) $invoiceDetail->paid_amount, 2) . " AUD.<br/><br/>
        The amount of " . number_format((float) $invoiceDetail->paid_amount, 2) . " AUD " . $payemntDes . " on " . $debit_date . ".<br/><br/>
        If you have any questions, please let us know.<br/><br/>
        Thanks,<br/><br/>
        BE FREE PTY. LTD.";

        $billingDetail['subject'] = $subject;
        $billingDetail['body'] = $body;
        $billingDetail['debitLine'] = $debit_line;
        $billingDetail['reference'] = implode(",", $reference);
        $data['largeInvoice'] = $largeInvoice;
        /* if ($largeInvoice == 1) {
          $data['invoiceDescriptionNext'] = $invoiceDescriptionNext;
          } */
        $data['invoiceDescription'] = $invoiceDescription;
        $data['invoice'] = $invoiceDetail;
        $data['billingDetail'] = $billingDetail;
        //$data['address'] = nl2br(htmlentities($billingDetail->address, ENT_QUOTES));
        $data['amountApplied'] = ($amountApplied == '') ? 0 : number_format($amountApplied, 2, ".", '');
        $path['entity_code'] = $billingDetail->code;
        $path['location'] = 'invoice';
        $getPath = self::getPath($path);
        @chmod($getPath, 0777);
        if ($PDF == 1) {
            $uploadPath = storageEfs() . self::getPath($path);
            @unlink($uploadPath);
            $pdf = PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('invoice', $data);
            $pdf->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif','logOutputFile' => null])->setPaper('a4', 'portable')->save($uploadPath . 'invoice ' . replaceString('/', '', $invoiceNo) . '.pdf');
            $pdfName = 'invoice ' . replaceString('/', '', $invoiceNo) . '.pdf';            
            // this is for auto invoic send by cron
            $pdfPath = $getPath;
            @chmod($pdfPath.$pdfName, 0777); 
            if ($type == 1) {
                return array('pdfPath' => $pdfPath, 'fileName' => $pdfName, 'billingDetail' => $billingDetail);
            } else {
                return array('pdfPath' => $pdfPath, 'fileName' => $pdfName);
            }
        } else {
            /* if(!empty($invoiceDescriptionNext)){
              $invoiceDescription = array_merge($invoiceDescription, $invoiceDescriptionNext);
              } */
            return array('invoice' => $invoiceDetail, 'invoiceDescription' => $invoiceDescription, 'billingDetail' => $billingDetail);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Invoice PDF generation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not generate PDF', ['error' => 'Could not generate PDF']);
          } */
    }

    public static function getReference($invoice) {

        if ($invoice->invoice_type == 'Advance') {
            $reference = 'Advance fees';
        } else if ($invoice->invoice_type == 'Setup') {
            $reference = 'BK Setup';
        } else if ($invoice->invoice_type == 'Formation') {
            $reference = 'SMSF Setup';
        } else if ($invoice->invoice_type == 'Audit') {
            $reference = 'SMSF Audit FY-' . date('y', strtotime($invoice->to_period));
        } else {
            $billing = \App\Models\Backend\BillingServices::where("id", $invoice->billing_id)->first();
            $freq = '';
            if ($billing->frequency_id == 1) {
                $freq = 'WE' . date('d/m', strtotime($invoice->to_period));
            } else if ($billing->frequency_id == 2) {
                $freq = 'FE' . date('d/m', strtotime($invoice->to_period));
            } else if ($billing->frequency_id == 6) {
                $freq = 'PE' . date('d/m', strtotime($invoice->to_period));
            } else if ($billing->frequency_id == 4) {
                $freq = date('M-Y', strtotime($invoice->to_period)) . 'QTR';
            } else if ($billing->frequency_id == 3) {
                $freq = date('M-y', strtotime($invoice->to_period));
            }
            if ($invoice->service_id == 1) {
                if ($invoice->is_fixed_fees == 1) {
                    $reference = 'BK ' . $freq;
                } else {
                    $reference = 'BK WIP ' . date('d/m', strtotime($invoice->to_period));
                }
            } else if ($invoice->service_id == 2) {
                if ($invoice->is_fixed_fees == 1) {
                    $reference = 'Pay' . $freq;
                } else {
                    $reference = 'Pay WIP ' . date('d/m', strtotime($invoice->to_period));
                }
            } else if ($invoice->service_id == 6) {
                $reference = 'Tax FY-' . date('y', strtotime($invoice->to_period));
            } else if ($invoice->service_id == 4 && $invoice->is_fixed_fees == 1) {
                $toDate = date("Y-m-01", strtotime($invoice->to_period));
                $reference = 'SMSF ' . date('M-y', strtotime('+1 month', strtotime($toDate)));
            } else if ($invoice->service_id == 5) {
                $toDate = date("Y-m-01", strtotime($invoice->to_period));
                $reference = 'Hosting ' . date('M-y', strtotime('-1 month', strtotime($toDate)));
            } else if ($invoice->service_id == 7) {
                $software = \App\Models\Backend\BillingSubscriptionSoftware::where("id", $billing->software_id)->first();
                if ($billing->software_id == '1') {
                    $toDate = date("Y-m-01", strtotime($invoice->to_period));
                    $reference = $software->software_plan . ' ' . date('M-y', strtotime('+2 month', strtotime($toDate)));
                } else {
                    $reference = $software->software_plan . ' ' . date('M-y');
                }
            }
        }
        return $reference;
    }

    public static function getPath($data) {
        $commanFolder = '/uploads/documents/';
        $uploadPath = storageEfs() . $commanFolder;

        //Check client code value
        if (isset($data['entity_code']) && $data['entity_code'] != '') {
            $mainFolder = $data['entity_code'];
        } else {// if client code not there that time document store in general 
            //$mainFolder = 'general';
            return 'Entity code missing';
        }

        //Create and check year directory 
        if (date("m") >= 7) {
            $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
            if (!is_dir($uploadPath . $dir)) {
                mkdir($uploadPath . $dir, 0777, true);
            }
        } else if (date("m") <= 6) {
            $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
            if (!is_dir($uploadPath . $dir)) {
                mkdir($uploadPath . $dir, 0777, true);
            }
        }
        $entityPath = $uploadPath . $dir . '/' . $mainFolder . '/';
        @chmod($entityPath, 0777);

        $location = '';
        if (isset($data['location']) && $data['location'] != '')
            $location = $data['location'];
        else
            return 'Location not define';

        $uploadPath = $uploadPath . $dir . '/' . $mainFolder . '/' . $location . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        } else {
            @chmod($uploadPath, 0777);
        }

        // Document path
        return $document_path = $commanFolder . $dir . '/' . $mainFolder . '/' . $location . '/';
    }

}

?>