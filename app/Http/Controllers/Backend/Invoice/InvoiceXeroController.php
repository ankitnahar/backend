<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Invoice;
use DB;
use App\Facades\XeroApi;

class InvoiceXeroController extends Controller {

    /**
     * Get invoice detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function importCSV(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'invoiceIds' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $invoiceId = $request->input('invoiceIds');
            $invoiceNo = Invoice::whereRaw("id IN ($invoiceId)")
                    ->select(DB::raw("GROUP_CONCAT(DISTINCT invoice_no) as invoice_no"))
                    ->first();
            //showArray($invoiceNo);exit;
            if ($invoiceNo->invoice_no != '') {
                $invoice = Invoice::getImportCSV($invoiceNo->invoice_no)->get();
            } else {
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice Detail does not exist', ['error' => 'The Invoice Detail does not exist']);
            }

            //For download excel if excel =1
            //format data in array
            $data = $invoice->toArray();
            $column = array();

            if (!empty($data)) {
                $column = array();
                $column[] = ['Sr.No', 'ContactName','reference', 'InvoiceNumber', 'InvoiceDate', 'DueDate','Description', 'Quantity', 'UnitAmount', 'AccountCode', 'TaxType'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['name'];
                        $columnData[] = $data['reference'];
                        $columnData[] = $data['invoice_no'];
                        $columnData[] = "0"; //date('d/m/Y');
                        $columnData[] = "0"; //date('d/m/Y', strtotime("+7 days"));
                        $columnData[] = $data['description'];
                        $columnData[] = "1";
                        $columnData[] = $data['amount'];
                        $columnData[] = $data['amount'] == '0.00' ? '' : $data['account_no'];
                        $columnData[] = $data['amount'] == '0.00' ? '' : 'GST on Income';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                // showArray($column);exit;
                //Update all invoice to awating payment stage

                Invoice::whereRaw("id IN ($invoiceId)")->update(["status_id" => 9]);
                //add log
                $invoiceIds = explode(",", $invoiceId);
                foreach ($invoiceIds as $ids) {
                    $log = \App\Models\Backend\InvoiceLog::addLog($ids, 9);
                }
                $d = date('d M Y');
                return exportExcelsheet($column, "InvoiceExportToCSV(" . $d . ")", 'xlsx', 'A1:K1');
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'), "Empty Data.", ['error' => 'Empty Data']);
            }
        } catch (\Exception $e) {
            app('log')->error("Download Invoice Export listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while download Invoice export list", ['error' => 'Server error.']);
        }
    }

    public function moveToDebtors(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'invoiceIds' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $invoiceId = $request->input('invoiceIds');
            $invoiceNos = Invoice::whereRaw("id IN ($invoiceId)")
                    ->select(DB::raw("invoice_no"))
                    ->get();
            foreach ($invoiceNos as $invoiceNo) {
                Invoice::where("invoice_no", $invoiceNo)->update(["debtors_stage" => "1"]);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice has been move to debtors successfully', ['data' => 'Invoice has been move to debtors successfully']);
        } catch (\Exception $e) {
            app('log')->error("Download Invoice Export listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while download Invoice export list", ['error' => 'Server error.']);
        }
    }

    public function invoiceMoveToXero() {
       // try {
            $xeroreply = self::invoiceSendToXero();
            return createResponse(config('httpResponse.SUCCESS'), $xeroreply, ['data' => $xeroreply]);
        /*} catch (\Exception $e) {
            app('log')->error("Send invoice to xero failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while send invoice to xero", ['error' => $e->getMessage()]);
        }*/
    }

    public function invoiceSendToPaid() {
        //try {
        set_time_limit(0);
        $xeroreply = self::updateXeroPaidInvoice();
        return createResponse(config('httpResponse.SUCCESS'), 'Invoice has been move to paid successfully', ['data' => $xeroreply]);
        /* } catch (\Exception $e) {
          app('log')->error("Send invoice to xero failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while send invoice to xero", ['error' => 'Error while send invoice to xero.']);
          } */
    }

    public static function invoiceSendToXero() {
        ini_set('max_execution_time', 0);
        $totalInvoice = Invoice::where("invoice.status_id", "3")->where("xero_responce", "1")
                        ->groupBy("invoice_no")->get()->count();

        if ($totalInvoice > 0) {
            $rowsPerReq = '10';
            $skip = 0;
            $counter = ceil($totalInvoice / $rowsPerReq);
            for ($start = 0; $start < $counter; $start++) {
                $invoice = Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("invoice_template as it", "it.invoice_no", "invoice.invoice_no")
                        ->leftjoin("billing_basic as b", "b.entity_id", "e.id")
                        ->leftjoin("state as s", "s.state_id", "b.state_id")
                        ->select("e.id", "e.billing_name", "e.xero_contact_id", "invoice.invoice_no", DB::raw("DATE(invoice.send_date) as send_date"), "s.state_name", "s.category_option_id", "it.reference")
                        ->where("invoice.status_id", "3")
                        ->where("invoice.parent_id", "0")
                        ->where("invoice.xero_responce", "1")
                        ->groupby("invoice_no")
                        ->orderby("invoice_no")
                        ->skip($skip)
                        ->take($rowsPerReq);
                $data = array();

                foreach ($invoice->get() as $row) {
                    if ($row->billing_name != '') {
                        $desc = \App\Models\Backend\InvoiceDescription::getDescription($row->invoice_no, $row->state_name, $row->category_option_id);
                        $data[] = array(
                            "EntityId" => $row->id,
                            "xero_contact_id" => $row->xero_contact_id,
                            "EntityName" => $row->billing_name,
                            "Type" => "ACCREC", # Accounting received.
                            "AmountType" => "Exclusive",
                            "LineAmountTypes" => "Exclusive",
                            "InvoiceNumber" => $row->invoice_no,
                            "Reference" => $row->reference, # small description ref 
                            "Date" => $row->send_date,
                            "DueDate" => date('Y-m-d', strtotime($row->send_date . ' +7 days')), # date('Y-m-d', strtotime("+3 days")),
                            "Status" => "AUTHORISED",
                            "LineItems" => $desc
                        );
                    }
                }
                if (!empty($data)) {
                    $skip = $rowsPerReq;
                    $XeroPHP = XeroApi::createInvoice($data);
                    /* if ($XeroPHP->getElementErrors()) {
                      return $XeroPHP->getElementErrors();
                      } */
                }
            }
        } else {
            return 'No Invoice Found';
        }
    }

    public static function updateXeroPaidInvoice() {
        $XeroPHP = XeroApi::getPaidInvoices();
         //showArray($XeroPHP);exit;
        if (!empty($XeroPHP)) {
            // $i = 0;
            foreach ($XeroPHP as $invoice) {

                // if($i ==4 ) {showArray($invoice);}
                $invoiceNumber = $invoice['InvoiceNumber'];
                $awatingPaymentInvoice = Invoice::where("invoice_no", $invoiceNumber)->where("status_id", 9)->first();
                if (isset($awatingPaymentInvoice->invoice_no) && $awatingPaymentInvoice->invoice_no != '') {
                    $billingName = \App\Models\Backend\Entity::where("id", $awatingPaymentInvoice->entity_id)->select("billing_name")->first();
                    // echo $i; $i++;
                    $XeroInvID = $invoice['InvoiceID'];
                    // move to paid with PAYMENT REVERSED                    
                    if ($invoice['AmountDue'] == '0.00' && $invoice['AmountPaid'] == '0.00' && $invoice['AmountCredited'] == '0.00') {
                        //  $this->invoiceMoveToPaid();
                    }

                    if (!empty($invoice['Payments'])) {
                        $paidAmount = 0;
                        foreach ($invoice['Payments'] as $payment) {
                            $paidAmount = $payment['Amount'];
                            $allocateCredit = 1;
                            $dateTime = $payment['Date']->format('Y-m-d H:i:s');
                            $dateArr = explode(" ", $dateTime);
                            $dateReplace = date("d/m/Y", strtotime($dateArr[0]));
                            $data = array();
                            $remamimgAmount = $invoice['AmountDue'];
                            if ($awatingPaymentInvoice->service_id == 6) {
                                $data = array('invoiceNumber' => $invoiceNumber,
                                    'amount' => $paidAmount,
                                    'total' => $awatingPaymentInvoice->paid_amount,
                                    'date' => $dateReplace, 'name' => $billingName->billing_name, 'remaningAmount' => $remamimgAmount);
                            }
                            self::saveInvoicePaidDetail($invoiceNumber, $paidAmount, $XeroInvID, $payment['PaymentID'], $payment['Reference'], $allocateCredit, $dateArr, $awatingPaymentInvoice->service_id, $data);
                        }
                        //where
                        

                        Invoice::where("invoice_no", $awatingPaymentInvoice->invoice_no)->update(["outstanding_amount" => $remamimgAmount]);
                    }
                    if (!empty($invoice['Overpayments'])) {
                        $allocateCredit = 3;
                        foreach ($invoice['Overpayments'] as $payment) {
                            $paidAmount = $payment['Total'];
                            $dateTime = $payment['Date']->format('Y-m-d H:i:s');
                            $dateArr = explode(" ", $dateTime);
                            $dateReplace = date("d/m/Y", strtotime($dateArr[0]));
                            $data = array();
                            $remamimgAmount = $invoice['AmountDue'];
                            if ($awatingPaymentInvoice->service_id == 6) {
                                $data = array('invoiceNumber' => $invoiceNumber,
                                    'amount' => $paidAmount,
                                    'total' => $awatingPaymentInvoice->paid_amount,
                                    'date' => $dateReplace, 'name' => $billingName->billing_name, 'remaningAmount' => $remamimgAmount);
                            }
                            self::saveInvoicePaidDetail($invoiceNumber, $paidAmount, $XeroInvID, $payment['OverpaymentID'], $payment['Reference'], $allocateCredit, $dateArr, $awatingPaymentInvoice->service_id, $data);
                        }
                         $remamimgAmount = $invoice['AmountDue'];

                        Invoice::where("invoice_no", $awatingPaymentInvoice->invoice_no)->update(["outstanding_amount" => $remamimgAmount]);
                    }
                    if (!empty($invoice['CreditNotes'])) {
                        foreach ($invoice['CreditNotes'] as $creditNote) {
                            $allocateCredit = 2;
                            $dateTime = $creditNote['Date']->format('Y-m-d H:i:s');
                            $dateArr = explode(" ", $dateTime);
                            $dateReplace = date("d/m/Y", strtotime($dateArr[0]));
                            $data = array();
                            $remamimgAmount = $invoice['AmountDue'];
                            if ($awatingPaymentInvoice->service_id == 6) {
                                $data = array('invoiceNumber' => $invoiceNumber,
                                    'amount' => $creditNote['Total'],
                                    'total' => $awatingPaymentInvoice->paid_amount,
                                    'date' => $dateReplace, 'name' => $billingName->billing_name, 'remaningAmount' => $remamimgAmount);
                            }
                            self::saveInvoicePaidDetail($invoiceNumber, $creditNote['Total'], $XeroInvID, $creditNote['CreditNoteID'], $creditNote['CreditNoteNumber'], $allocateCredit, $dateArr, $awatingPaymentInvoice->service_id, $data);
                        }
                         $remamimgAmount = $invoice['AmountDue'];

                        Invoice::where("invoice_no", $awatingPaymentInvoice->invoice_no)->update(["outstanding_amount" => $remamimgAmount]);
                    }

                    if ($invoice['Status'] == 'PAID') {
                        $paidDate = $invoice['FullyPaidOnDate']->format('Y-m-d');
                        self::invoiceMoveToPaid($invoiceNumber,$paidDate);
                    }
                }
                else{
                    continue;
                }
            }
            //return 'All Invoice Move to Paid';
        } else {
            return $XeroPHP;
        }
    }

    public static function sendMailTAX($data) {
        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "INVPAIDTAX")->first();
        if ($emailTemplate->is_active) {
            $search = array('NUMBER', 'AMOUNT', 'TOTAL', 'DATE', 'CLIENT_NAME', 'NUMBER', 'REMAIN');
            $replaceSubject = array($data['invoiceNumber']);
            $replaceContent = array($data['invoiceNumber'], $data['amount'], $data['total'], $data['date'], $data['name'], $data['invoiceNumber'], $data['remaningAmount']);
//                                   showArray($replaceContent);
            $subjectReplaced = str_replace($search, $replaceSubject, $emailTemplate->subject);
            $content = html_entity_decode(str_replace($search, $replaceContent, $emailTemplate->content));
            $store['to'] = $emailTemplate->to;
            $store['cc'] = $emailTemplate->cc;
            $store['bcc'] = $emailTemplate->bcc;
            $store['subject'] = $subjectReplaced;
            $store['content'] = $content;
            cronStoreMail($store);
        }
    }

    public static function saveInvoicePaidDetail($InvoiceNumber, $Amount, $XeroInvID, $PaymentID, $Reference, $allocateCredit, $dateArr, $serviceId, $data) {
        $payment = \App\Models\Backend\InvoicePaidDetail::where("xero_payment_id", $PaymentID)->count();
        if ($payment == 0) {

            if ($serviceId == 6) {

                self::sendMailTAX($data);
            }

            $invoicePaidDetail = \App\Models\Backend\InvoicePaidDetail::create([
                        'invoice_no' => $InvoiceNumber,
                        'paid_amt' => number_format((float) $Amount, 2, '.', ''),
                        'xero_invoice_id' => $XeroInvID,
                        'paid_date' => !empty($dateArr) ? $dateArr[0] : date("Y-m-d"),
                        'xero_payment_id' => $PaymentID,
                        'xero_reference' => $Reference,
                        'allocate_credit' => $allocateCredit,
                        'created_by' => 1,
                        'created_on' => date('Y-m-d H:i:s')]);
        }
    }

    public static function invoiceMoveToPaid($InvoiceNumber,$paidDate) {
        $id = Invoice::where("invoice_no", $InvoiceNumber)
                ->update(["status_id" => 4,
            "payment_date" => $paidDate,
            "outstanding_amount" => 0,
            "debtors_stage" => 0]);
        self::addInvoiceLog($InvoiceNumber, 4);
        return $id;
    }

    public static function addInvoiceLog($InvoiceNumber, $statusId) {
        $logInvoice = Invoice::where("invoice_no", $InvoiceNumber)->get();
        foreach ($logInvoice as $invo) {
            $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invo->id, $statusId);
        }
    }

}

?>