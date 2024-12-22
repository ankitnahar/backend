<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Invoice;
use DB;

class InvoicePreviewController extends Controller {

    /**
     * Store bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function preview(Request $request, $id) {
        // try {

        $invoice = Invoice::find($id);

        $entityName = \App\Models\Backend\Entity::select("trading_name as name")->find($invoice->entity_id);
        $wipPeriod = '';
        //get Billing Info
        $billingBasic = \App\Models\Backend\Billing::where("entity_id", $invoice->entity_id)->first();
        // if client is on merge and invoice no generate
        if ($invoice->invoice_type == 'Auto invoice' || ($billingBasic->merge_invoice == 1 && $invoice->invoice_no != '')) {
            $invoiceDeatil = Invoice::where("entity_id", $invoice->entity_id)
                            ->where("invoice_no", "=", $invoice->invoice_no)
                            ->where("status_id", "!=", 5)
                            ->where("parent_id", 0)->get();
        } else {
            //for related entity
            if ($invoice->parent_id == 0) {
                $invoiceDeatil = Invoice::where("parent_id", $id)->orWhere("id", $id)->get();
            } else {
                $invoiceDeatil = Invoice::where("parent_id", $invoice->parent_id)->orWhere("id", $invoice->parent_id)->get();
            }
            //calculate period
            $wipPeriod = '';
            $oneOffInvoice = array('Advance', 'Setup', 'Audit', 'Formation');
            if (!in_array($invoice->invoice_type, $oneOffInvoice)) {
                $period = $invoice->from_period != '0000-00-00' ? date("d-m-Y", strtotime($invoice->from_period)) . ' to ' . date("d-m-Y", strtotime($invoice->to_period)) : 'till ' . date("d-m-Y", strtotime($invoice->to_period));
                $wipPeriod = ' ( ' . $period . ' ) ';
            }
        }

        //showArray($invoiceDeatil);exit;
        //get invoice saved description

        $grossAmount = $netAmount = $gstAmount = $surchargeAmount = $totalAmount = $discountAmount = $discountAdvance = 0;
        $description = array();
        // for advance setup formation and audit
        $oneOffInvoice = array('Advance', 'Setup', 'Audit', 'Formation');
        foreach ($invoiceDeatil as $row) {
            // echo $row->gross_amount;exit;
            // advance fees invoice
            if ($row->invoice_type == 'Advance') {
                $descriptionData[0]['description'] = 'Advance fees';
                $descriptionData[0]['amount'] = $row->gross_amount;
                $descriptionData[0]['inv_account_id'] = '';
            }
            // formation fees invoice
            if ($row->invoice_type == 'Formation') {
                $descriptionData[0]['description'] = 'Company Setup';
                $descriptionData[0]['amount'] = $row->gross_amount;
                $descriptionData[0]['inv_account_id'] = '';
            }
            // Setup fees invoice
            if ($row->invoice_type == 'Setup') {
                $descriptionData[0]['description'] = 'Initial setup cost';
                $descriptionData[0]['amount'] = $row->gross_amount;
                $descriptionData[0]['inv_account_id'] = '2';
            }
            //Audit Fees invoice
            if ($row->invoice_type == 'Audit') {
                $descriptionData[0]['description'] = 'Audit fees';
                $descriptionData[0]['amount'] = $row->gross_amount;
                $descriptionData[0]['inv_account_id'] = '';
            }
            if (!in_array($row->invoice_type, $oneOffInvoice)) {
                $billingDetail = \App\Models\Backend\BillingServices::getBilling($row->billing_id);
                if ($row->service_id == 1) {
                    $description[] = $this->bkDescription($row, $billingDetail, $billingBasic->full_time_resource);
                }
                if ($row->service_id == 2) {
                    $description[] = $this->payrollDescription($row, $billingDetail, $billingBasic->full_time_resource);
                }
                if ($row->service_id == 6) {
                    $description[] = $this->taxDescription($billingDetail->fixed_fee);
                }
                if ($row->service_id == 4) {
                    $description[] = $this->smsfDescription($row->gross_amount);
                }
                if ($row->service_id == 5) {
                    $description[] = $this->hostingDescription($row->gross_amount, $billingDetail);
                }
                if ($row->service_id == 7) {
                    $description[] = $this->subscriptionDescription($row->gross_amount, $billingDetail);
                }
            } else {
                $description[] = $descriptionData;
            }

            // amount calculation
            $invoiceIds[] = $row->id;
            $grossAmount = $grossAmount + (float) $row->gross_amount;
            $netAmount = $netAmount + (float) $row->net_amount;
            if ($row->discount_type != 'None') {
                if ($row->discount_type == 'Advance') {
                    $discountAdvance = $discountAdvance + (float) $row->discount_amount;
                } else {
                    if($netAmount > 0)
                    $discountAmount = $discountAmount + (float) $row->discount_amount;
                }
            }
            $surchargeAmount = $surchargeAmount + (float) $row->surcharge_amount;
            $gstAmount = $gstAmount + (float) $row->gst_amount;
            $totalAmount = $totalAmount + (float) $row->paid_amount;
        }
        $amountCalculation = array("gross_amount" => $grossAmount,
            "net_amount" => $netAmount,
            "discount_advance" => $discountAdvance,
            "discount_amount" => $discountAmount,
            "surcharge_amount" => $surchargeAmount,
            "gst_amount" => $gstAmount,
            "paid_amount" => $totalAmount);
        $invoiceIds = implode(",", $invoiceIds);
        $invoiceDescription = \App\Models\Backend\InvoiceDescription::whereRaw("invoice_id IN ($invoiceIds)")
                        ->where("hide", "0")
                        ->where("description", "!=", "''")->orderby("sort_order")->get();


        return createResponse(config('httpResponse.SUCCESS'), 'Wip Preview Detail', ['entityName' => $entityName->name, 'period' => $wipPeriod, 'merge_invoice' => $billingBasic->merge_invoice, 'invoicePreview' => $description, 'invoiceDescription' => $invoiceDescription, 'amountCalc' => $amountCalculation]);
        /* } catch (\Exception $e) {
          app('log')->error("Invoice Preview creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get invoice preview detail', ['error' => 'Could not get invoice preview detail']);
          } */
    }

    public static function bkdescription($invoice, $billing, $fullTimeResource) {

        if ($fullTimeResource == 0) {
            $code = 1;
        } else {
            $code = 69;
        }
        $arrShowDesc = array();
        $i = 0;
        $wipPeriod = dateFormat($invoice->from_period) . ' to ' . dateFormat($invoice->to_period);
        if ($invoice->from_period == '0000-00-00')
            $previewPeriod = 'till ' . dateFormat($invoice->to_period);
        else
            $previewPeriod = 'from ' . $wipPeriod;

        $FF = $billing->fixed_total_amount;
        // bk fixed
        if ($FF > 0) {
            $freqline = self::frequencyLine($invoice->to_period);
            if (isset($freqline[$billing->frequency_id])) {
                $arrShowDesc[0]['description'] = 'Fixed bookkeeping fees for the ' . $freqline[$billing->frequency_id];
                $arrShowDesc[0]['amount'] = $FF;
                $arrShowDesc[0]['inv_account_id'] = $code;
            } else {
                $arrShowDesc[0]['description'] = 'Fixed bookkeeping fees';
                $arrShowDesc[0]['amount'] = $FF;
                $arrShowDesc[0]['inv_account_id'] = $code;
            }
            $i = 1;
        } else {
            $entitytrading = \App\Models\Backend\Entity::where("id", $invoice->entity_id)->first();
            $arrShowDesc[0]['description'] = $entitytrading->trading_name;
            $arrShowDesc[0]['amount'] = '';
            $arrShowDesc[0]['inv_account_id'] = '';
            $arrShowDesc[1]['description'] = 'Being for work performed during the period ' . $previewPeriod;
            $arrShowDesc[1]['amount'] = '';
            $arrShowDesc[1]['inv_account_id'] = '';
            $i = 2;
        }
        $dicountAccountCode = 1;
        $arrShowDesc = self::bkpayrollDetaildescription($invoice, $billing, $FF, $arrShowDesc, $previewPeriod, $i, $dicountAccountCode, $fullTimeResource);
        return $description = $arrShowDesc;
    }

    public static function payrolldescription($invoice, $billing, $fullTimeResource) {
        if ($fullTimeResource == 0) {
            $code = 7;
        } else {
            $code = 69;
        }
        $arrShowDesc = array();
        $i = 0;
        $wipPeriod = dateFormat($invoice->from_period) . ' to ' . dateFormat($invoice->to_period);
        if ($invoice->from_period == '0000-00-00')
            $previewPeriod = 'till ' . dateFormat($invoice->to_period);
        else
            $previewPeriod = 'from ' . $wipPeriod;

        //get frequency
        $freq = \App\Models\Backend\Frequency::find($billing->frequency_id);
        $FF = $billing->fixed_fee;
        // payroll fixed
        if ($FF > 0) {
            $arrShowDesc[0]['description'] = $freq->frequency_name . ' fixed fees during the period ' . $previewPeriod;
            $arrShowDesc[0]['amount'] = '';
            $arrShowDesc[0]['inv_account_id'] = '';
            $arrShowDesc[1]['description'] = 'Payroll';
            $arrShowDesc[1]['amount'] = $FF;
            $arrShowDesc[1]['inv_account_id'] = $code;
            $i = 2;
        }
        // payroll variable
        else {
            $arrShowDesc[0]['description'] = 'Being for work performed during the period ' . $previewPeriod;
            $arrShowDesc[0]['amount'] = '';
            $arrShowDesc[0]['inv_account_id'] = '';
            $i = 1;
        }
        $dicountAccountCode = 7;
        $arrShowDesc = self::bkpayrollDetaildescription($invoice, $billing, $FF, $arrShowDesc, $previewPeriod, $i, $dicountAccountCode, $fullTimeResource);

        return $description = $arrShowDesc;
    }

    public static function bkpayrollDetaildescription($invoice, $billing, $FF, $arrShowDesc, $previewPeriod, $i, $dicountAccountCode, $fullTimeResource) {
        //get invoice account

        $invoiceAccount = \App\Models\Backend\InvoiceAccount::invoiceAccountDetail();

        //for subactivity 701,707 and 708 if inc in ff store fixed value
        if ($invoice->service_id == 2) {
            $fixedNoEmpSubActivity = array(404, 417, 422, 463);
            $payrollOption = \App\Models\Backend\TimesheetPayrollOption::get()->pluck("type_name", "id")->toArray();
        } else {
            $fixedNoEmpSubActivity = array(501, 505, 601, 607, 701, 707, 708);
        }
        $subactivityDetail = \App\Models\Backend\BillingServicesSubactivity::getEntityBillingSubactivity($invoice->created_on, $invoice->billing_id, $fixedNoEmpSubActivity);
//showArray($subactivityDetail);exit;
        //get all timesheet in between this invoice period and billing statusis not charge and carry forward
        $timesheetList = \App\Models\Backend\Timesheet::getInvoicePreviewTimesheet($invoice, $invoice->service_id);
//echo getSQL($timesheetList);exit;
        if ($timesheetList->count() > 0) {
            foreach ($timesheetList->get() as $timesh) {
                $timeshArray[$timesh->master_activity_id][] = $timesh;
            }

            //showArray($timeshArray);exit;
            $flagExtraOnce = FALSE;
            foreach ($timeshArray AS $master_id => $timesheets) {

                $extraAmt = \App\Models\Backend\InvoiceMasterUnitCalc::invoiceUnitCalc($invoice->id);

                if (!empty($extraAmt[$master_id]['amount']) && $extraAmt[$master_id]['amount'] != '0.00') {
                    if (!$flagExtraOnce && $FF > 0) {
                        $arrShowDesc[$i]['description'] = 'Being for additional work performed during the period ' . $previewPeriod . ' (not included in fixed fee arrangement)';
                        $arrShowDesc[$i]['amount'] = '';
                        $arrShowDesc[$i]['inv_account_id'] = '';

                        $flagExtraOnce = TRUE;
                        $i++;
                    }
                    $arrShowDesc[$i]['description'] = $extraAmt[$master_id]['master_name'] . ' :';
                    $arrShowDesc[$i]['amount'] = $extraAmt[$master_id]['amount'];
                    if ($fullTimeResource == 0) {
                        $accountId = \App\Models\Backend\MasterActivity::where("id", $master_id)->select('inv_account_id')->first();
                        $arrShowDesc[$i]['inv_account_id'] = $accountId->inv_account_id;
                    } else {
                        $arrShowDesc[$i]['inv_account_id'] = 69;
                    }
                    $arrInvDesc = array();
                    $i++;
                    foreach ($timesheets as $timesheet) {
                        // if amount is zero that time we will not calculate invoice description
                        if (($timesheet->invoice_amt == '0' || $timesheet->invoice_amt == '0.00') && (isset($subactivityDetail['fixedValue']['404']) && $timesheet->subactivity_code != '404' 
                                &&  $timesheet->no_of_value < $subactivityDetail['fixedValue']['404'])) {
                            continue;
                        }

                        $line = $timesheet->invoice_desc;
                        if ($timesheet->bank_cc_account_no != '') {
                            $account_no = $timesheet->bank_cc_account_no;
                            // match dash, space & numbers
                            if (preg_match('/^[-0-9 .\-]+$/i', $account_no))
                                $account_no = 'XXXXXX' . substr($account_no, -4);

                            $bank = $timesheet->bank_cc_name . '-' . $account_no;
                            $line = $timesheet->invoice_desc;
                            $line = replaceString('TRANS', $timesheet->no_of_value, $line);
                            $line = replaceString('FROMDATE', dateFormat($timesheet->start_date), $line);
                            $line = replaceString('TODATE', dateFormat($timesheet->end_date), $line);
                            $line = replaceString('BANKNAME', $bank, $line);
                        }
                        if (stringsearch($line, 'EMPCNT')) {
                            if ($FF > 0 && isset($subactivityDetail['fixedValue'][$timesheet->subactivity_code])) {
                                if ($timesheet->no_of_value > $subactivityDetail['fixedValue'][$timesheet->subactivity_code]) {
                                    $timesheet->no_of_value = $timesheet->no_of_value - $subactivityDetail['fixedValue'][$timesheet->subactivity_code];

                                    $remaning404 = 'additional ' . $timesheet->no_of_value;
                                    $line = replaceString('EMPCNT', $remaning404, $line);
                                } else {
                                    $line = replaceString('EMPCNT', $timesheet->no_of_value, '');
                                }
                            } else {
                                $line = replaceString('EMPCNT', $timesheet->no_of_value, $line);
                            }
                            $line = ($timesheet->no_of_value == 1) ? replaceString("employees", "employee", $line) : $line;
                        }
                        if ($timesheet->subactivity_code == '470') {
                            if (stringsearch($line, 'EMPCNT')) {
                                $line = replaceString('EMPCNT', $timesheet->no_of_value, $line);
                                $line = ($timesheet->no_of_value == 1) ? replaceString("employees", "employee", $line) : $line;
                            }
                        }
                        if (stringsearch($line, 'PAYSLIPCNT')) {
                            $line = replaceString('PAYSLIPCNT', $timesheet->no_of_value, $line);
                        }
                        if (stringsearch($line, 'TMPER')) {
                            $line = replaceString('TMPER', $timesheet->period_startdate . ' to ' . $timesheet->period_enddate, $line);
                        }
                        if ($timesheet->payroll_option_id != '') {
                            $arrDDoption = explode(",", $timesheet->payroll_option_id);
                            foreach ($arrDDoption as $option) {
                                $notes = $payrollOption[$option];
                                if ($timesheet->subactivity_code == '448') {
                                    $arrShowDesc[$i]['description'] = $notes;
                                    $line = '';
                                    $i++;
                                } else {
                                    $line = replaceString('DDOPTION', $notes, $line);
                                }
                            }
                        }

                        if (stringsearch($line, 'YEAR')) {
                            $line = replaceString('YEAR', date('Y'), $line);
                        }
                        if (stringsearch($line, 'PERIOD')) {
                            $line = replaceString('PERIOD', dateFormat($timesheet->start_date) . ' to ' . dateFormat($timesheet->end_date), $line);
                        }

                        if (stringsearch($line, 'FREQUENCY')) {
                            $line = replaceString('FREQUENCY', strtolower($timesheet->frequency_name), $line);
                        }

                        if (stringsearch($line, 'NUMBER')) {
                            if ($FF > 0 && !empty($timesheet->no_of_value)) {

                                if (isset($subactivityDetail['fixedValue'][$timesheet->subactivity_code]) && $timesheet->no_of_value > $subactivityDetail['fixedValue'][$timesheet->subactivity_code]) {
                                    $timesheet->no_of_value = 'additional ' . ($timesheet->no_of_value - $subactivityDetail['fixedValue'][$timesheet->subactivity_code]);
                                    $line = replaceString('NUMBER', $timesheet->no_of_value, $line);
                                } else {
                                    $line = replaceString('NUMBER', $timesheet->no_of_value, '');
                                }
                            } else {
                                $line = replaceString('NUMBER', $timesheet->no_of_value, $line);
                            }
                        }
                        if ($timesheet->no_of_value > 0 && $timesheet->no_of_value < 6 && $timesheet->name_of_employee != '') {
                            if (stringsearch($line, 'EMPNAME')) {
                                $nameOfEmployee = array();
                                $nameOfEmployeeArray = json_decode($timesheet->name_of_employee, true);
                                if (!empty($nameOfEmployeeArray))
                                    foreach ($nameOfEmployeeArray as $keyEmployeename => $valueEmployeename) {
                                        $nameOfEmployee[] = $valueEmployeename['first_name'] . ' ' . $valueEmployeename['last_name'];
                                    }
                                if (!empty($nameOfEmployee)) {
                                    $nameOfEmployee = implode(",", $nameOfEmployee);
                                    $empName = rtrim($nameOfEmployee, ",");
                                    $line = replaceString('EMPNAME', $empName, $line);
                                }
                            }
                        } else {
                            if (stringsearch($line, '(EMPNAME)')) {
                                $line = replaceString('(EMPNAME)', '', $line);
                            }
                        }
                        if (stringsearch($line, 'PERYER')) {
                            $year = explode("-", $timesheet->end_date);
                            $line = replaceString('PERYER', $year[0], $line);
                        }


                        if (!empty($line)) {
                            if (stringsearch($line, '~')) {
                                $lines = explode("~", $line);
                                foreach ($lines as $line) {
                                    $arrShowDesc[$i]['description'] = $line;
                                    $arrShowDesc[$i]['amount'] = '';
                                    $arrShowDesc[$i]['inv_account_id'] = '';
                                    $i++;
                                }
                            } else {
                                $arrShowDesc[$i]['description'] = $line;
                                $arrShowDesc[$i]['amount'] = '';
                                $arrShowDesc[$i]['inv_account_id'] = '';
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        return $arrShowDesc;
        //exit;
    }

    public function taxdescription($fixedfee) {
        $arrShowDesc = array();
        $FF = !empty($fixedfee) ? $fixedfee : 0;
        $fy = date("Y");
        $tempExplode = explode("-", date("Y-m-d"));
        if ($tempExplode[1] >= '07') {
            $fy = date('Y');
        } else {
            $fy = date("Y", strtotime("-1 year"));
        }
        // tax fixed
        if ($FF > 0) {
            $arrShowDesc[0]['description'] = 'Fixed Fees as agreed for preparation and lodgement of tax return for FY ' . date('Y');
            $arrShowDesc[0]['amount'] = $FF;
            $arrShowDesc[0]['inv_account_id'] = "14";
        }
        // tax variable
        else {
            $arrShowDesc[]['description'] = 'Tax return ' . $fy;
            $arrShowDesc[]['description'] = 'Review of profit & loss to ensure figures reflect true trading for the year';
            $arrShowDesc[]['description'] = 'Fixed Fees as agreed for preparation and lodgement of Tax Return FY ' . $fy;
            $arrShowDesc[]['description'] = 'Preparation of financial statements and lodgement of tax returns for FY ' . $fy . ' including';
            $arrShowDesc[]['description'] = 'Review of last year tax accounts & tax returns to ensure no items are missed and figures reflect true changes in the business';
            $arrShowDesc[]['description'] = 'Review of balance sheet items to ensure figures correctly reflect true financial situation. Review of Profit & Loss to ensure figures reflect true trading for the year. Review of the balance of the debtors, creditors.';
            $arrShowDesc[]['description'] = 'Preparation of depreciation schedule  and  journal entry in accounts to reflect current year depreciation';
            $arrShowDesc[]['description'] = 'Download reports from ATO portal to check lodgement status and review & reconcile client\'s integrated tax account,  BAS figures  etc with accounts';
            $arrShowDesc[]['description'] = 'Entry of business figures including income, expenses, depreciation changes and balance sheet items to complete business schedule in tax return. Reconcile with accounts to ensure correct entry.';
            $arrShowDesc[]['description'] = 'Review and entering of details in the tax return for the R & D Tax concession';
            $arrShowDesc[]['description'] = 'Calculate losses to be carried forward and prepare Losses schedule in tax return';
            $arrShowDesc[]['description'] = 'Correspondence with client as necessary & gathering of information provided by client';
            $arrShowDesc[]['description'] = 'Review of financial statements and tax returns for FY ' . $fy . ' by Sydney Manager';
            $arrShowDesc[]['description'] = 'Review of accounts to determine year end status for tax purposes. Review and calculation of tax payable if any and determination of strategies to ensure best tax position for the group.';
            $arrShowDesc[]['description'] = 'Advised current position and estimate profit and tax. Also advised about extra points to be taken care of  to ensure that tax payable remains minimum.';
            $arrShowDesc[]['description'] = 'Review of accounts and tax planning for ' . $fy . ' FY by Senior Tax Manager';
            $arrShowDesc[]['description'] = 'Preparation  and lodgement of tax returns for FY ' . $fy . ' including Review of last year tax  returns to ensure no items are missed, Download reports from ATO portal to check lodgement status and review of pre filling report, income tax accounts.';
            $arrShowDesc[]['description'] = 'Preparation of work related expenses schedule';
            $arrShowDesc[]['description'] = 'Entering of details of the distribution from the trust';
            $arrShowDesc[]['description'] = 'Preparation of rental property schedule';
            $arrShowDesc[]['description'] = 'Preparation of capital gain schedule';
        }
        return $description = $arrShowDesc;
    }

    public static function smsfDescription($gross_amount) {
        $arrShowDesc = array();
        $FYear = '';
        if (date("Y-m-d") >= date("Y-07-01")) {
            $FYear = ' (For FY ' . date('Y', strtotime(date('Y') . ' + 1 year')) . ')';
        } else {
            $FYear = ' (For FY ' . date('Y') . ')';
        }
        $description = "SMSF compliance fees for " . date('M') . " " . date('Y') . " " . $FYear;

        $arrShowDesc[0]['description'] = $description;
        $arrShowDesc[0]['amount'] = $gross_amount;
        $arrShowDesc[0]['inv_account_id'] = "10";
        return $description = $arrShowDesc;
    }

    public static function hostingDescription($gross_amount, $billing) {
        $arrShowDesc = array();
        $hostingUser = \App\Models\Backend\BillingHostingUser::where("entity_id", $billing->entity_id)->where("is_active", "1")->get();
        $premUsers = $specialUsers = $basicUsers = '';
        $basicRate = $premRate = '0';
        $userDesc = "(";
        foreach ($hostingUser as $row) {
            $type = $row->plan_type;
            if ($type == 'P') {
                $premUsers++;
                $premRate = $row->rate;
            } else if ($type == 'B') {
                $basicUsers++;
                $basicRate = $row->rate;
            } else if ($type == 'S')
                $specialUsers .= "1 User X $" . $row->rate . ',';
        }
        if (!empty($premUsers)) {
            if ($premUsers > 1)
                $userline = " Users";
            else
                $userline = " User";
            $userDesc .= $premUsers . $userline . " X $" . $premRate . ",";
        }
        if (!empty($basicUsers)) {
            if ($basicUsers > 1)
                $userline = " Users";
            else
                $userline = " User";
            $userDesc .= $basicUsers . $userline . " X $" . $basicRate . ",";
        }

        if (!empty($specialUsers))
            $userDesc .= $specialUsers;
        $userDesc = rtrim($userDesc, ',');
        $userDesc .= ")";
        $description = 'MYOB hosting fees for the month of ' . date('M') . '-' . date('Y') . $userDesc;

        $arrShowDesc[0]['description'] = $description;
        $arrShowDesc[0]['amount'] = $gross_amount;
        $arrShowDesc[0]['inv_account_id'] = "48";

        return $description = $arrShowDesc;
    }

    public static function subscriptionDescription($gross_amount, $billing) {
        $arrShowDesc = array();
        //get subscription software detail       
        $softwareAccount = \App\Models\Backend\BillingSubscriptionSoftware::find($billing->software_id);
        $arrSoftware = \App\Models\Backend\BillingSubscriptionSoftware::get()->pluck("software_plan", "id")->toArray();
        // showArray($arrSoftware);exit;
        //$final_year = date('M-Y', strtotime('+2 month'));
        $final_year = date("M-Y",strtotime("+2 month",strtotime(date("Y-m-01",strtotime("now")))));
        if ($billing->software_id == '1') {
            $description = $arrSoftware[$billing->software_id] . " subscription - " . $arrSoftware[$billing->plan_id] . " for the month of " . $final_year;
        } else {
            $description = $arrSoftware[$billing->software_id] . " subscription - " . $arrSoftware[$billing->plan_id] . " for the month of " . date('M') . "-" . date('Y');
        }

        $arrShowDesc[0]['description'] = $description;
        $arrShowDesc[0]['amount'] = $gross_amount;
        $accountId = '60';
        if ($billing->software_id == 1) {
            $accountId = '60';
        } else if ($billing->software_id == 2) {
            $accountId = '66';
        } else if ($billing->software_id == 3) {
            $accountId = '62';
        } else if ($billing->software_id == 4) {
            $accountId = '64';
        }
        $arrShowDesc[0]['inv_account_id'] = $accountId;
        return $description = $arrShowDesc;
    }

    // frequency line in description
    public static function frequencyLine($to_period) {
        $toPeriodStr = strtotime($to_period);
        $month = date("M", $toPeriodStr);
        $year = date("Y", $toPeriodStr);
        $tillPeriod = date("d-m-Y", strtotime($to_period));

        $freqline['1'] = "week ending " . $tillPeriod;
        $freqline['2'] = "fortnight ending " . $tillPeriod;
        $freqline['3'] = "month of " . $month . "-" . $year;
        $freqline['4'] = $month . "-" . $year . " QTR";
        $freqline['6'] = "period ending " . $tillPeriod;
        $freqline['10'] = "period - " . $tillPeriod;

        return $freqline;
    }

    /**
     * save Invoice description
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function saveInvoiceDescription(Request $request, $id) {
        //try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'status_id' => 'required|numeric',
                'description' => 'required|array'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            if ($request->input('status_id') == 11 || $request->input('status_id') == 7) {
                $invoice = Invoice::where("id", $id)->where("parent_id", "0");
                if ($invoice->count() == 0) {
                    return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'You can not Approve or Merge from related entity, Please go to the main entity and then Approve and Merge']);
                }
            }
            
            $invoice = Invoice::find($id);
            // store invoice details
            $description = $request->get('description');
            
            foreach ($description as $row) {
                $amount = isset($row['amount']) ? $row['amount'] : '0';
                $accoId = isset($row['inv_account_id']) ? $row['inv_account_id'] : '';
                if ($amount > 0 && ($accoId == 0 || $accoId == '')) {
                    return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'Please Add Account code If amount is there']);
                }
            }
            
            foreach ($description as $row) {
                if (trim($row['description']) != '') {
                    $descriptionData = [
                        'invoice_id' => $id,
                        'inv_account_id' => isset($row['inv_account_id']) ? $row['inv_account_id'] : '',
                        'description' => $row['description'],
                        'amount' => isset($row['amount']) ? $row['amount'] : '',
                        'hide' => 0,
                        'sort_order' => $row['sort_order'],
                        'created_on' => date("Y-m-d:h:i:s"),
                        'created_by' => loginUser()];
                    if (isset($row['id']) && $row['id'] != 0) {
                        \App\Models\Backend\InvoiceDescription::where("id", $row['id'])->update($descriptionData);
                    } else {
                        \App\Models\Backend\InvoiceDescription::Insert($descriptionData);
                    }
                } else if ($row['id'] != 0) {
                    \App\Models\Backend\InvoiceDescription::find($row['id'])->delete();
                }
            }
            if ($request->input('status_id') == '11' && ($invoice->invoice_no == '' || $invoice->invoice_no == null)) {
                //sent to client
                $invoiceAlready = Invoice::leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                                ->select("invoice_no")
                                ->where("b.merge_invoice", "1")
                                ->where("invoice.entity_id", $invoice->entity_id)
                                ->where("invoice.status_id", $request->input('status_id'))->first();
                $is_merge = 0;
                if (isset($invoiceAlready->invoice_no)) {
                    $invoiceNo = $invoiceAlready->invoice_no;
                    $is_merge = 1;
                } else {
                    $invoiceNo = $this->generateInvoiceNo($invoice->entity_id);
                }
                //echo $invoiceNo;exit;
                //update invoice no in invoice and invoice description
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $id)->update(["invoice_no" => $invoiceNo]);
                Invoice::where("id", $id)->orWhere("parent_id", $id)->update(["invoice_no" => $invoiceNo, "status_id" => $request->input('status_id')]);

                Invoice::where("invoice_no", $invoiceNo)->update(["is_merge" => $is_merge]);
                // add log
                $allinvoice = Invoice::select("id")->where("invoice_no", $invoiceNo)->get();
                foreach ($allinvoice as $invoiceId) {
                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoiceId->id, $request->input('status_id'));
                }
            } else if ($request->input('status_id') == '7' && ($invoice->invoice_no == '' || $invoice->invoice_no == null)) {
                //Merge case
                $invoiceAlready = Invoice::leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                                ->select("invoice_no")
                                ->where("b.merge_invoice", "1")
                                ->where("invoice.invoice_no", "!=", "''")
                                ->where("invoice.entity_id", $invoice->entity_id)
                                ->where("invoice.status_id", $request->input('status_id'))->first();
                $is_merge = 0;
                if (isset($invoiceAlready->invoice_no)) {
                    $invoiceNo = $invoiceAlready->invoice_no;
                    $is_merge = 1;
                } else {
                    $invoiceNo = $this->generateInvoiceNo($invoice->entity_id);
                }
                $statusId = $request->input('status_id');
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $id)->update(["invoice_no" => $invoiceNo]);
                if ($invoice->status_id == 2) {
                    $statusId = 7;
                    Invoice::where("id", $invoice->id)->update(["invoice_no" => $invoiceNo, "status_id" => '7']);
                } else if ($invoice->status_id == 7) {
                    $statusId = 11;
                    Invoice::where("entity_id", $invoice->entity_id)->where("status_id", "7")->update(["invoice_no" => $invoiceNo, "status_id" => '11']);
                }
                Invoice::where("invoice_no", $invoiceNo)->update(["is_merge" => $is_merge]);

                //add log
                $allinvoice = Invoice::select("id")->where("invoice_no", $invoiceNo)->get();
                foreach ($allinvoice as $invoiceId) {
                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoiceId->id, $statusId);
                }
            } else if ($invoice->invoice_no != '' || $invoice->invoice_no != null) {
                $statusId = $request->input('status_id');
                if ($request->input('status_id') == 7) {
                    $statusId = 11;
                }
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $id)->update(["invoice_no" => $invoice->invoice_no]);
                Invoice::where("invoice_no", $invoice->invoice_no)->update(["status_id" => $statusId]);
                $allinvoice = Invoice::select("id")->where("invoice_no", $invoice->invoice_no)->get();
                foreach ($allinvoice as $invoiceId) {
                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoiceId->id, $statusId);
                }
            } else {
                Invoice::where("id", $id)->orWhere("parent_id", $id)->update(["status_id" => $request->input('status_id')]);
                // add log
                $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($id, $request->input('status_id'));
            }

            // ADD user Hierarchy
            if ($request->input('status_id') == '6' && ($invoice->service_id == 1 || $invoice->service_id == 2 || $invoice->service_id == 6)) {
                $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $invoice->entity_id)->where("service_id", $invoice->service_id)->first();
                $invoiceUserHierarchy = \App\Models\Backend\InvoiceUserHierarchy::where("invoice_id", $id)->update(['user_hierarchy' => $entityAllocation->allocation_json]);
            }


            return createResponse(config('httpResponse.SUCCESS'), 'Invoice Description has been added successfully', ['message' => "Invoice Description has been added successfully"]);
        /*} catch (\Exception $e) {
            app('log')->error("Invoice Description creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add invoice description', ['error' => 'Could not add invoice description']);
        }*/
    }

    public static function generateInvoiceNo($entityId) {
        $code = \App\Models\Backend\Entity::select(DB::raw("LENGTH(code) AS lengthClientCode"))->find($entityId);
        $clientCodeLengthTemp = (isset($code->lengthClientCode) && !empty($code->lengthClientCode)) ? $code->lengthClientCode : 8;
        $clientCodeLength = (int) $clientCodeLengthTemp + 2;

        $invoice = Invoice::getInvoiceNo($entityId, $clientCodeLength);
        if (($invoice->invoice_no == '') || ($invoice->invoice_no == 0)) {
            return $invoiceNo = $invoice->code . "/01";
        } else {
            $invoiceNo = $invoice->code . "/" . sprintf("%02d", $invoice->invoice_no + 1);
            $countInvoice = Invoice::where("invoice_no", $invoiceNo)->count();
            if ($countInvoice > 0) {
                return $invoiceNo = $invoice->code . "/" . sprintf("%02d", $invoice->invoice_no + 2);
            } else {
                return $invoiceNo;
            }
        }
    }

    /**
     * Invoice Status particular Invoice
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function invoiceAccount() {
        try {
            $invoiceAccount = \App\Models\Backend\InvoiceAccount::where("is_active", 1)->orderBy("id")->get();
            //invoice log
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice Account List', ['data' => $invoiceAccount]);
        } catch (\Exception $e) {
            app('log')->error("Invoice Account detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get invoice account details.', ['error' => 'Could not get invoice account details.']);
        }
    }

}

?>