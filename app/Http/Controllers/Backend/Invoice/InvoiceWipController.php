<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Invoice;
use DB;

class InvoiceWipController extends Controller {

    //in this status we can not change any value

    private $statusNotChange = array(3, 4, 9,11, 10);

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function wipinvoice(Request $request, $id) {
        // try {
        $invoice = Invoice::find($id);
        if (!$invoice)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Invoice does not exist', ['error' => 'The Invoice does not exist']);

        $entityName = \App\Models\Backend\Entity::select("trading_name as name")->find($invoice->entity_id);
        $wipPeriod = '';
        //get Billing Info
        $billingBasic = \App\Models\Backend\Billing::where("entity_id", $invoice->entity_id)->first();

        $invoiceStatusIds = array(1, 2, 6);
        // if client is on merge and invoice no generate
        if (!in_array($invoice->status_id, $invoiceStatusIds) && $invoice->is_merge == 1) {

            $invoiceDeatil = Invoice::leftjoin("services as s", "s.id", "invoice.service_id")
                            ->select("invoice.id", "s.service_name as name")
                            ->where("invoice.entity_id", $invoice->entity_id)
                            ->where("invoice.invoice_no", $invoice->invoice_no)
                            ->whereRaw("status_id Not IN (1,2,6)")
                            ->where("invoice.parent_id", 0)->get();
        } else {
            //for related entity
            $invoiceDeatil = Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                    ->select("invoice.id", "e.trading_name as name")
                    ->Where("invoice.id", $id);

            if ($invoice->parent_id != '0') {
                $invoiceDeatil = $invoiceDeatil->orWhere("invoice.id", $invoice->parent_id)
                        ->orWhere("invoice.parent_id", $invoice->parent_id);
            } else {
                $invoiceDeatil = $invoiceDeatil->orWhere("invoice.parent_id", $id);
            }
            $invoiceDeatil = $invoiceDeatil->get();


            //calculate period
            $wipPeriod = '';
            $oneOffInvoice = array('Advance', 'Setup', 'Audit', 'Formation');
            if (!in_array($invoice->invoice_type, $oneOffInvoice)) {
                $period = $invoice->from_period != '0000-00-00' ? date("d-m-Y", strtotime($invoice->from_period)) . ' to ' . date("d-m-Y", strtotime($invoice->to_period)) : 'till ' . date("d-m-Y", strtotime($invoice->to_period));
                $wipPeriod = ' ( ' . $period . ' ) ';
            }
        }
//showArray($invoiceDeatil);exit;
        //get Billing Info
        $billingDetail = \App\Models\Backend\BillingServices::getBilling($invoice->billing_id);
        //showArray($billingDetail);exit;
        if (!$billingDetail)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Detail does not exist', ['error' => 'The Billing Detail does not exist']);
        $billing = array();
        $billingNotes = '';
        if ($billingDetail->billing_notes != '') {
            $billingNotes = $billingDetail->billing_notes . ',';
        }
        if ($billingDetail->notes != '') {
            $billingNotes .= $billingDetail->notes;
        }
        $billingNotes = rtrim($billingNotes, ",");
        //get invoice notes
        $invoiceNotes = \App\Models\Backend\InvoiceNotes::with("createdBy:id,userfullname", "modifiedBy:id,userfullname")->where("invoice_id", $invoice->id)->get();

        // for advance setup formation and audit
        $oneOffInvoice = array('Advance', 'Setup', 'Audit', 'Formation');
        if (!in_array($invoice->invoice_type, $oneOffInvoice)) {
            if ($invoice->service_id == 1 || $invoice->service_id == 2 || $invoice->service_id == 6) {//for bk payroll and tax
                $billing = $this->billingData($billingDetail, $invoice->status_id);
                $invoice = $this->bkpayrolltaxInvoice($invoice, $billingDetail);
            } else if ($invoice->service_id == 4) {//for smsf
                if (($invoice->gross_amount == '0' || $invoice->gross_amount == '') && ($invoice->status_id == 2 || $invoice->status_id == 1)) {
                    $invoice = $this->smsfInvoice($invoice, $billingDetail);
                }
                $invoice = array("invoice" => $invoice);
            } else if ($invoice->service_id == 5) {//for hosting
                if (($invoice->gross_amount == '0' || $invoice->gross_amount == '') && ($invoice->status_id == 2 || $invoice->status_id == 1)) {
                    $invoice = $this->hostingInvoice($invoice, $billingDetail);
                }
                $invoice = array("invoice" => $invoice);
            } else if ($invoice->service_id == 7) {//for subscription             
                $billing = array('Fiexd fee' => array(), 'RPH' => array(), 'masterName' => array(), 'subscription_discount' => $billingDetail->discount);

                if (($invoice->gross_amount == '0' || $invoice->gross_amount == '') && ($invoice->status_id == 2 || $invoice->status_id == 1)) {
                    $invoice = $this->subscriptionInvoice($invoice, $billingDetail);
                }
                $invoice = array("invoice" => $invoice);
            }
        } else {
            $billing = $billingDetail;
            $invoice['card_surcharge'] = $billingDetail->surcharge;
            $invoice = array("invoice" => $invoice);
        }
//        return var_dump($invoice);
        $wipInvoice = array('entityName' => $entityName->name, 'period' => $wipPeriod, 'topDetail' => $invoiceDeatil, 'invoice' => $invoice, 'BillingDetail' => $billing, 'BillingNotes' => $billingNotes, 'InvoiceNotes' => $invoiceNotes);
        return createResponse(config('httpResponse.SUCCESS'), 'Wip Detail', ['data' => $wipInvoice]);
        /* } catch (\Exception $e) {
          app('log')->error("Bank updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get wip detail.', ['error' => 'Could not get wip detail.']);
          } */
    }

    public function billingData($billing, $statusId) {
        //if client invoice is on auto invoice that time recurre and manual invoice get FF zero
        $s = 0;
        $ff = array();
        if ($statusId != 10) {
            $ff[$s]['service_id'] = $billing->service_name;
            $ff[$s]['fixed_fee'] = $billing->fixed_fee;
            $s++;
        }
        //get rph

        if ($billing->service_id == 1 && $statusId != 10) {
            $masterIds = \App\Models\Backend\MasterActivity::getMasterIdServiceWise($billing->service_id);
            $RPH['default'] = $billing->default_rph;
            // // for bk get other service detail like AR,AP,DM,BKpayroll
            //get RPH
            $bkArray = array(5, 6, 7, 22);
            foreach ($masterIds as $masterId) {
                if (in_array($masterId->id, $bkArray)) {
                    $RPH[$masterId->id] = $billing->ff_rph;
                } else {
                    $RPH[$masterId->id] = $billing->default_rph;
                }
                $masterName[$masterId->id] = $masterId->name;
            }
            $bkOtherServices = \App\Models\Backend\BillingBKRPH::leftjoin("services as s", "s.id", "billing_bk_rph.service_id")->where("billing_id", $billing->id)
                            ->groupBy("billing_bk_rph.service_id")->orderBy("billing_bk_rph.created_on", "desc");

            if ($bkOtherServices->count() > 0) {
                foreach ($bkOtherServices->get() as $other) {
                    if ($other->inc_in_ff == 1) {
                        $ff[$s]['service_id'] = $other->service_name;
                        $ff[$s]['fixed_fee'] = $other->fixed_fee;
                        $s++;
                    }
                    if ($other->service_id == 11)
                        $RPH[8] = $other->rph;
                    else if ($other->service_id == 8)
                        $RPH[9] = $other->rph;
                    else if ($other->service_id == 9)
                        $RPH[10] = $other->rph;
                    else if ($other->service_id == 10)
                        $RPH[11] = $other->rph;
                }
            }
        }else {
            $masterIds = \App\Models\Backend\MasterActivity::getMasterIdServiceWise($billing->service_id);
            $RPH['default'] = $billing->ff_rph;
            foreach ($masterIds as $masterId) {
                $RPH[$masterId->id] = $billing->ff_rph;
                $masterName[$masterId->id] = $masterId->name;
            }
        }
        return $billing = array('Fiexd fee' => $ff, 'RPH' => $RPH, 'masterName' => $masterName, 'subscription_discount' => '');
    }

    /**
     * calculate bkpayrolltax invoice 
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function bkpayrolltaxInvoice($invoice, $billing) {

        //get timesheet
        $timesheetVal = $this->getTimesheet($invoice, $billing);

        $extraAmount = $timesheetVal['totalCalculation']['total_extra_amount'];
        //if invoice not in paid ,ready to export send to client and awating payment 

        if ($invoice->service_id == 1) {
            $fixedUnit = $billing->fixed_total_unit;
            $fixedFee = $billing->fixed_total_amount;
            $rph = $billing->default_rph;
        } else {
            $fixedFee = $billing->fixed_fee;
            $rph = $billing->ff_rph;
            $fixedUnit = ($fixedFee > 0 ) ? round(($fixedFee * ($billing->ff_rph / 10))) : '0';
        }
        if ($invoice->status_id == 10) {
            $fixedFee = 0;
            $fixedUnit = 0;
        }
        if (!in_array($invoice->status_id, $this->statusNotChange)) {
            $invoice = $this->grandTotalCalc($invoice, $fixedFee, $invoice['extra_amount'], $billing->surcharge, $invoice->discount_type, $invoice->discount_amount);
        }
        //calculate fixedUnit and extra unit      


        $extraUnit = 0;
        if ($rph > 0) {
            $extraUnit = ($extraAmount > 0 ) ? round(($invoice['extra_amount'] / ( $rph / 10)), 2) : '0';
        }

        //get total grand total unit 
        $invoice = $this->grandUnitCalc($invoice, $timesheetVal['totalCalculation']['total_unit'], $timesheetVal['totalCalculation']['total_carry'], $fixedUnit, $extraUnit);
        return array('invoice' => $invoice, 'timesheetDetail' => $timesheetVal);
    }

    /**
     * calculate hosting invoice amount
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function hostingInvoice($invoice, $billing) {
        $hostingAmount = \App\Models\Backend\BillingHostingUser::where("entity_id", $invoice->entity_id)->where("is_active", "1")->sum('rate');

        $invoice = $this->grandTotalCalc($invoice, $hostingAmount, 0, $billing->surcharge, 'None', '0');
        return $invoice;
    }

    /**
     * calculate smsf invoice amount
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function smsfInvoice($invoice, $billing) {
        if ($invoice->invoice_type != 'Audit') {
            $monthly = $billing->monthly_amount;
            $fixedffdate = $billing->ff_start_date;
            $invoicemonth = explode("-", $invoice->to_period);
            if ($billing->frequency_id == 3) {
                if ($invoicemonth[1] == '05') {
                    $ffyear = explode("-", $fixedffdate);
                    $cyear = date('Y');
                    $ff_cmonth = $ffyear[1];
                    if ($ff_cmonth > '06' && $ff_cmonth <= '12')
                        $ffyear[0] = $ffyear[0] + 1;
                    if ($ffyear[0] == $cyear)
                        $grossAmt = $billing->balance_amount;
                    else
                        $grossAmt = $billing->monthly_amount;
                } else
                    $grossAmt = $billing->monthly_amount;
            } else
                $grossAmt = $billing->smsf_fee;
        } else
            $grossAmt = $invoice->gross_amount;

        $invoice = $this->grandTotalCalc($invoice, $grossAmt, 0, $billing->surcharge, 'None', '0');

        return $invoice;
    }

    /**
     * calculate subscription invoice amount
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function subscriptionInvoice($invoice, $billing) {
        //calculate grand total
        $discountType = '';
        $discountAmount = 0;
        if ($billing->discount > '0' && $billing->discount > '0.00') {
            $discountType = 'Fixed';
            $discountAmount = $billing->discount;
        }
        $invoice = $this->grandTotalCalc($invoice, $billing->standard_fee, 0, $billing->surcharge, $discountType, $discountAmount);

        return $invoice;
    }

    /**
     * calculate timesheet on invoice period
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function getTimesheet($invoice, $billing) {

        $masterId = \App\Models\Backend\MasterActivity::where("service_id", $invoice->service_id)->select(DB::raw("GROUP_CONCAT(id) as master_id"))
                        ->where("is_active", "1")->first();

        //get all timesheet in between this invoice period and billing statusis not charge and carry forward
        $timesheetList = \App\Models\Backend\Timesheet::getInvoiceTimesheet($invoice, $masterId->master_id);
        // showArray($timesheetList->toSql());exit;
        //assign variable
        $timesheetArray = $data = $totNoOfValue = $subActivityCalculationShow = array();
        $total_extra_amount = $totalunit = $totalcarry = $totalwriteoff = 0;
        //show subactivity calculation  
        if ($timesheetList->count() > 0) {
            // for bk subactivity calculation
            if ($invoice->service_id == 1) {
                // get bk other service RPH
                $RPH['5'] = $RPH['6'] = $RPH['7'] = $RPH['22'] = $billing->ff_rph;
                $RPH['3'] = $RPH['4'] = $RPH['12'] = $RPH['23'] = $RPH['24'] = $RPH['34'] = $billing->default_rph;
                $ff['1'] = $ff['5'] = $ff['6'] = $ff['7'] = $ff['22'] = $billing->bk_in_ff;
                $bkOther = \App\Models\Backend\BillingBKRPH::where("billing_id", $billing->id)->get();
                foreach ($bkOther as $row) {
                    if ($row->service_id == 8) {
                        $ff[9] = $row->inc_in_ff;
                        $RPH[9] = $row->rph;
                    }
                    if ($row->service_id == 9) {
                        $ff[10] = $row->inc_in_ff;
                        $RPH[10] = $row->rph;
                    }
                    if ($row->service_id == 10) {
                        $ff[11] = $row->inc_in_ff;
                        $RPH[11] = $row->rph;
                    }
                    if ($row->service_id == 11) {
                        $ff[8] = $row->inc_in_ff;
                        $RPH[8] = $row->rph;
                    }
                }
                //get total no_of_value
                $subactivityCodeForValue = array(201, 202, 228, 501, 505, 601, 607, 701, 707, 708);
                //for subactivity 701,707 and 708 if inc in ff store fixed value
                $fixedNoEmpSubActivity = array(701, 707, 708);
                //master array those always charge in RPH
                $masterIds = array(3, 4, 12, 23, 24, 34);
                //get all vaisible subactivity detail 
                $subactivityDetail = \App\Models\Backend\BillingServicesSubactivity::getEntityBillingSubactivity($invoice->created_on, $billing->id, $subactivityCodeForValue);
                //showArray($subactivityDetail);exit;
                //check array for period wise calculation
                $subActivityPeriod = array('2001', '2002', '2101', '2102', '709');

                $subTotalUnit = \App\Models\Backend\Timesheet::bkSubactivityWipTotalUnit($invoice->id, $invoice->entity_id, $invoice->from_period, $invoice->to_period);
            } else if ($invoice->service_id == 2) {
                $payrollOption = \App\Models\Backend\TimesheetPayrollOption::get()->pluck("type_name", "id")->toArray();
                //for subactivity 404,417 and 422 if inc in ff store fixed value
                $subactivityCodeForValue = $fixedNoEmpSubActivity = array(301, 2508, 404, 417, 422, 463);
                //get all vaisible subactivity detail 
                $subactivityDetail = \App\Models\Backend\BillingServicesSubactivity::getEntityBillingSubactivity($invoice->created_on, $billing->id, $fixedNoEmpSubActivity);
                //echo $billing->id;
              // showArray($subactivityDetail);exit;
                //if client is not on FF that time 404 and 417 take MIN value for no of employe
                $GLOBALS['404'] = $GLOBALS['422'] = 1;                
               
                $subactivity = \App\Models\Backend\Timesheet::activityPeriod($invoice->id, $invoice->entity_id, $invoice->from_period, $invoice->to_period, $fixedNoEmpSubActivity);
                //showArray($subactivity->get()->toArray());exit;
                $subactivityDetail['fixedValue'][404] = 0;
                if ($subactivity->count() > 0) {
                    $i =0;
                    foreach ($subactivity->get() as $rowSub) {
                        if($rowSub->subactivity_code == '404' && $subactivityDetail['subActivity']['404']['inc_in_ff'] ==1){
                            $i = $i +1;
                        }
                        if($subactivityDetail['subActivity']['404']['inc_in_ff'] ==1){
                        $GLOBALS[$rowSub->start_date . '-' . $rowSub->end_date] = 1;
                        }else if($rowSub->subactivity_code != '404'){
                            $GLOBALS[$rowSub->start_date . '-' . $rowSub->end_date] = 1;
                        }
                        
                    }
                    if($subactivityDetail['subActivity']['404']['inc_in_ff'] ==1)
                    $subactivityDetail['fixedValue'][404] = $subactivityDetail['subActivity'][404]['no_of_value'] * $i;
                }
            }
            if ($invoice->service_id != 6) {
                $subTotalvalueInvoice = \App\Models\Backend\Timesheet::subactivityTotalValue($invoice->id, $invoice->service_id, $invoice->entity_id, $invoice->from_period, $invoice->to_period, $subactivityCodeForValue);
                //showArray($subTotalvalueInvoice);exit;
                if (!empty($subTotalvalueInvoice)) {
                    $subActivityCalculationShow = $this->subActivityCalculationShow($subTotalvalueInvoice, $subactivityDetail, $subactivityCodeForValue);
                }
            }

            $i = 0;
            foreach ($timesheetList->get() as $timesheet) {
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['timesheet_id'] = $timesheet->id;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['subactivity_code'] = $timesheet->subactivity_code;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['subactivity_name'] = $timesheet->subactivity_name;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['worksheet_start_date'] = $timesheet->start_date;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['worksheet_end_date'] = $timesheet->end_date;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['date'] = $timesheet->date;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['userfullname'] = $timesheet['assignee']['userfullname'];
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['units'] = $timesheet->units;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['notes'] = $timesheet->notes;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['billing_status'] = ($invoice->status_id == 10) ? 3 : $timesheet->billing_status;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['bank_cc_name'] = $timesheet->bank_cc_name;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['bank_cc_account_no'] = $timesheet->bank_cc_account_no;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['no_of_value'] = $timesheet->no_of_value;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['name_of_employee'] = $timesheet->name_of_employee;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['extra_value'] = $timesheet->extra_value;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['period_startdate'] = $timesheet->period_startdate;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['period_enddate'] = $timesheet->period_enddate;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['reviewer'] = $timesheet->reviewer_id;
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['payroll_option'] = isset($payrollOption[$timesheet->payroll_option_id]) ? $payrollOption[$timesheet->payroll_option_id] : "";
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['carry_forward_invoice_ids'] = ($timesheet->carry_forward_invoice_ids != null) ? $timesheet->carry_forward_invoice_ids : '';
                $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['amount'] = 0;
                $amount = 0;

                //calculate unit and amount for uncharge and charge timesheet for this invoice
                if (!in_array($invoice->status_id, $this->statusNotChange)) {
                    //define units
                    $sub['units'] = $timesheet->units;
                    // for tax
                    // if client is on FF then all subactivity amount is zero
                    //if client is not on FF amount will caculated as per RPH
                    if ($invoice->service_id == 6) {
                        $sub['RPH'] = $billing->ff_rph;
                        if ($billing->inc_in_ff == 1) {
                            $amount = "0";
                        } else {
                            $amount = $this->subactivityCalc("RPH", $sub);
                        }
                    } else if ($invoice->service_id == 1) {
                        /* for Bk 
                         * we will check if client is on fixed fee that time we will charge all visiable subactivity as per subactivity rule
                         * if client is not on FF that time all Bk subactivity charge with RPH
                         */
                        if (in_array($timesheet->master_activity_id, $masterIds)) {
                            $sub['RPH'] = $RPH[$timesheet->master_activity_id];
                            $subActivityRule = 'RPH';
                            $amount = $this->subactivityCalc($subActivityRule, $sub);
                        } else if (isset($ff[$timesheet->master_activity_id]) && $ff[$timesheet->master_activity_id] == 1) {
                            if ($timesheet->visible == 1) {
                                $sub['RPH'] = $RPH[$timesheet->master_activity_id];
                                //check ff on visiable subactivity
                                $fixedFee = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff'] : '0';
                                if ($fixedFee == 1) {
                                    //calulation for 701,707,708
                                    if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity)) {
                                        $sub['fixed_value'] = $subactivityDetail['fixedValue'][$timesheet->subactivity_code];
                                        $sub['no_of_emp'] = $timesheet->no_of_value;
                                        $sub['price'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'];
                                        $subactivityDetail['fixedValue'][$timesheet->subactivity_code] = max($subactivityDetail['fixedValue'][$timesheet->subactivity_code] - $timesheet->no_of_value, 0);
                                    }
                                    $subActivityRule = $timesheet->ff_rule;
                                } else {
                                    $subActivityRule = $timesheet->not_ff_rule;
                                    // check subactivity Period wise amount (amount calulated in one time in one period)
                                    if (in_array($timesheet->subactivity_code, $subActivityPeriod)) {
                                        if (!isset($subactivityPeriod[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date])) {
                                            $subactivityPeriod[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date] = 1;
                                            $sub['min'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'] : '0';
                                            $sub['units'] = $subTotalUnit[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date];
                                        } else {
                                            $subActivityRule = 'ZER';
                                        }
                                    }
                                }
                                $amount = $this->subactivityCalc($subActivityRule, $sub);
                            } else { //if client if on FF that time all visible = 0 subactivity amount should be zero
                                $amount = 0;
                            }
                        } else {
                            //default assign for all subactivity if client is not on FF
                            $subActivityRule = 'RPH';
                            //assign RPH as per master id 
                            //echo $timesheet->master_activity_id.'<br/>'.$timesheet->id;
                            $sub['RPH'] = isset($RPH[$timesheet->master_activity_id]) ? $RPH[$timesheet->master_activity_id] : '0.00';
                            $sub['units'] = $timesheet->units;
                            // check subactivity Period wise amount (amount calulated in one time in one period)
                            if (in_array($timesheet->subactivity_code, $subActivityPeriod)) {
                                if (!isset($subactivityPeriod[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date])) {
                                    $subactivityPeriod[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date] = 1;
                                    $sub['min'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'] : '';
                                    $subActivityRule = 'MIN';
                                    $sub['units'] = $subTotalUnit[$timesheet->subactivity_code][$timesheet->start_date . '-' . $timesheet->end_date];
                                } else {
                                    $subActivityRule = 'ZER';
                                }
                            }
                            if ($timesheet->subactivity_code == '9' || $timesheet->subactivity_code == '148' || $timesheet->subactivity_code == '1230') {
                                $sub['RPH'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'];
                            }

                            $amount = $this->subactivityCalc($subActivityRule, $sub);
                        }
                    } elseif ($invoice->service_id == 2) {
                        if ($timesheet->visible == 1) {
                            /* for payroll 
                             * if client is on FF and subactivity also is on FF that time we will not change FF entry other then we will charge all visible subactivity as per rule
                             * if client is not on FF that time we will charge all visible entry as per rule
                             */
                            //check client is on FF or not
                             $ff = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff'] : '0';

                            if ($billing->inc_in_ff == 1) {
                                $sub['RPH'] = $billing->ff_rph;
                                //check subactivity is on fixed fee or not
                                if ($ff == 1) {
                                    if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity)) {
                                        //$sub['fixed_value'] = $subactivityDetail['fixedValue'][$timesheet->subactivity_code]; 
                                        if($timesheet->subactivity_code == 404){
                                        $sub['fixed_value'] = $subactivityDetail['fixedValue'][404];
                                        }else{
                                            $sub['fixed_value'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'];
                                        }
                                        $sub['no_of_emp'] = $timesheet->no_of_value;                                        
                                        $sub['RPH'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'];
                                        $sub['min'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'];
                                        $subactivityDetail['fixedValue'][$timesheet->subactivity_code] = max($subactivityDetail['fixedValue'][$timesheet->subactivity_code] - $timesheet->no_of_value, 0);
                                        $sub['wip_from_period'] = $timesheet->start_date;
                                        $sub['wip_to_period'] = $timesheet->end_date;
                                        //showArray($sub);exit;
                                    }
                                    $subActivityRule = $timesheet->ff_rule;
                                } else {
                                    $subActivityRule = $timesheet->not_ff_rule;
                                    $sub['RPH'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'];
                                    $sub['no_of_emp'] = $timesheet->no_of_value;
                                    if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity)) {
                                        $sub['fixed_value'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'];
                                        $sub['min'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'];
                                        $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] = max($subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] - $timesheet->no_of_value, 0);
                                        $sub['wip_from_period'] = $timesheet->start_date;
                                        $sub['wip_to_period'] = $timesheet->end_date;
                                    }
                                    // 414,425,466 for same day charge one time only
                                    if ($timesheet->subactivity_code == 301 || $timesheet->subactivity_code == 2508 || $timesheet->subactivity_code == 414 || $timesheet->subactivity_code == 425 || $timesheet->subactivity_code == 466) {
                                        if (isset($GLOBALS[$timesheet->subactivity_code][$timesheet->date]))
                                            $GLOBALS[$timesheet->subactivity_code][$timesheet->date] = 0;
                                        else {
                                            $GLOBALS[$timesheet->subactivity_code][$timesheet->date] = 1;
                                        }
                                        $sub['timesheet_date'] = $timesheet->date;
                                    }
                                }
                            } else {
                                $subActivityRule = $timesheet->not_ff_rule;
                                $sub['RPH'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['price']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'] : '0.00';
                                $sub['no_of_emp'] = $timesheet->no_of_value;
                                if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity) && isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'])) {
                                    $sub['fixed_value'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] : '0';
                                    $sub['min'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'];
                                    $sub['wip_from_period'] = $timesheet->start_date;
                                    $sub['wip_to_period'] = $timesheet->end_date;
                                    $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] = max($subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] - $timesheet->no_of_value, 0);
                                }else{
                                    $sub['fixed_value'] = 0;
                                    $sub['min'] = 0;
                                    $sub['wip_from_period'] = $timesheet->start_date;
                                    $sub['wip_to_period'] = $timesheet->end_date;
                                    $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] = 0;
                                }

                                // 414,425,466 for same day charge one time only
                                if ($timesheet->subactivity_code == 301 || $timesheet->subactivity_code == 2508 || $timesheet->subactivity_code == 414 || $timesheet->subactivity_code == 425 || $timesheet->subactivity_code == 466) {
                                    if (isset($GLOBALS[$timesheet->subactivity_code][$timesheet->date]))
                                        $GLOBALS[$timesheet->subactivity_code][$timesheet->date] = 0;
                                    else {
                                        $GLOBALS[$timesheet->subactivity_code][$timesheet->date] = 1;
                                    }
                                    $sub['timesheet_date'] = $timesheet->date;
                                }
                            }

                            $amount = $this->subactivityCalc($subActivityRule, $sub, $ff, $timesheet->subactivity_code);
                        } else {
                            $amount = 0;
                        }
                    }
                   // echo $amount;

                    $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['amount'] = $amount;
                } else if ($invoice->status_id == '10') {
                    $amount = 0;
                } else { // if invoice is in send to client,paid,awating payment,ready to export stage
                    $amount = $timesheet->invoice_amt;
                    $timesheetArray[$timesheet->master_activity_id][$timesheet->task_name][$i]['amount'] = $timesheet->invoice_amt;
                }
                if ($timesheet->billing_status == '0' && $timesheet->billing_status == '1') {
                    //calculate total unit   
                    $totalunit = $totalunit + $timesheet->units;
                } else if ($timesheet->billing_status == 2) {//calculate total carry unit
                    $totalcarry = $totalcarry + $timesheet->units;
                } else {
                    $totalwriteoff = $totalwriteoff + $timesheet->units;
                }

                $total_extra_amount = round($total_extra_amount + $amount, 2);
                $i++;
            }
            $totalCalculation = array('total_extra_amount' => $total_extra_amount,
                'total_unit' => $totalunit,
                'total_carry' => $totalcarry);
            //get master unit
            $masterUnitCalc = \App\Models\Backend\InvoiceMasterUnitCalc::invoiceUnitCalc($invoice->id);

            $data = array('timesheet' => $timesheetArray, 'subActivity' => $subActivityCalculationShow, 'masterUnit' => !empty($masterUnitCalc) ? $masterUnitCalc : array(), 'totalCalculation' => $totalCalculation);
        } else {
            $totalCalculation = array('total_extra_amount' => 0,
                'total_unit' => 0,
                'total_carry' => 0);
            $data = array('timesheet' => array(), 'subActivity' => array(), 'master_unit' => array(), 'totalCalculation' => $totalCalculation);
        }
        return $data;
    }

    public static function subactivityCalc($rule, $subactivity, $ff = Null, $subactivityCode = Null) {        
        switch ($rule) {
            case "MIN":
                $amt = round((($subactivity['RPH'] * $subactivity['units']) / 10), 2);
                if ($subactivity['min'] > $amt)
                    return $amount = $subactivity['min'];
                else
                    return $amount = $amt;
                break;
            case "BKEMP":
                if ($subactivity['fixed_value'] >= $subactivity['no_of_emp']) {
                    return $amount = 0;
                } else if ($subactivity['fixed_value'] < $subactivity['no_of_emp']) {
                    $remaning = ($subactivity['no_of_emp']) - ($subactivity['fixed_value']);
                    return $amount = $remaning * $subactivity['price'];
                }
                break;
            case "NOP":
                return $amount = $subactivity['RPH'] * $subactivity['no_of_emp'];
                break;
            case "463":
                if ($subactivity['no_of_emp'] > 0) {
                    $amt = ($subactivity['RPH'] * $subactivity['no_of_emp']);

                    if ($subactivity['min'] > $amt)
                        return $amount = $subactivity['min'];
                    else
                        return $amount = $amt;
                }else {
                    return 0;
                }
                break;
            case "417":
                if (empty($subactivity['fixed_value'])) {
                    return $amount = $subactivity['no_of_emp'] * $subactivity['RPH'];
                } else if ($subactivity['fixed_value'] >= $subactivity['no_of_emp']) {
                    if ($ff == 1)
                        return $amount = 0;
                    else {
                        if (isset($GLOBALS[$subactivity['wip_from_period'] . '-' . $subactivity['wip_to_period']]) && $GLOBALS[$subactivity['wip_from_period'] . '-' . $subactivity['wip_to_period']] == '1') {
                            $amount = $subactivity['min'];
                            $GLOBALS[$subactivity['wip_from_period'] . '-' . $subactivity['wip_to_period']] = '0';
                        } else
                            $amount = '0';
                        return $amount;
                    }
                } else if ($subactivity['fixed_value'] < $subactivity['no_of_emp']) {
                    $remaning = $subactivity['no_of_emp'] - $subactivity['fixed_value'];
                    if ($ff == 1)
                        return $amount = $remaning * $subactivity['RPH'];
                    else
                        return $amount = $subactivity['min'] + ($remaning * $subactivity['RPH']);
                }
                break;
            case "EMP":
                if (empty($subactivity['fixed_value'])) {
                    return $amount = $subactivity['no_of_emp'] * $subactivity['RPH'];
                } else if ($subactivity['fixed_value'] >= $subactivity['no_of_emp']) {
                    if ($ff == 1)
                        return $amount = 0;
                    else
                        return $amount = $min;
                } else if ($subactivity['fixed_value'] < $subactivity['no_of_emp']) {
                    $remaning = $subactivity['no_of_emp'] - $subactivity['fixed_value'];
                    if ($ff == 1)
                        return $amount = $remaning * $subactivity['RPH'];
                    else
                        return $amount = $subactivity['min'] + ($remaning * $subactivity['RPH']);
                }
                break;
            case '404':
                if ($subactivity['fixed_value'] > $subactivity['no_of_emp']) {
                    if ($ff == 1)
                        return $amount = 0;
                    else if ($GLOBALS[$subactivityCode] == '1') {
                        $GLOBALS[$subactivityCode] = '0';
                        return $amount = $subactivity['min'];
                    } else
                        return $amount = 0;
                }
                else if ($subactivity['fixed_value'] <= $subactivity['no_of_emp']) {    
                      $remaning = $subactivity['no_of_emp'];
                    if ($ff == 1){
                        $remaning = $subactivity['no_of_emp']-$subactivity['fixed_value'];
                        return $amount = $remaning * $subactivity['RPH'];
                    }
                    else if ($GLOBALS[$subactivityCode] == '1') {
                         $GLOBALS[$subactivityCode] = '0';
                         $remaning = $subactivity['no_of_emp'] - $subactivity['fixed_value'];
                        return $amount = $subactivity['min'] + ($remaning * $subactivity['RPH']);
                    } else
                        return $amount = ($remaning * $subactivity['RPH']);
                }
                break;
            case "RPH":
                return $amount = round(($subactivity['RPH'] * $subactivity['units']) / 10, 2);
                break;
            case "AMT":
                if (($subactivityCode == 301 || $subactivityCode == 2508 || $subactivityCode == 414 || $subactivityCode == 425 || $subactivityCode == 466 ) && $GLOBALS[$subactivityCode][$subactivity['timesheet_date']] == 1)
                    return $amount = $subactivity['RPH'];
                else
                    return $amount = 0;
                break;
            case "ZER":
                return $amount = 0;
                break;
            default:
                return $amount = 0;
                break;
        }
    }

    public function grandTotalCalc($invoice, $ff, $extraAmount, $surcharge, $discountType, $discount) {
        try {
            $invoice['ff_amount'] = $ff;
            $invoice['extra_amount'] = $extraAmount;
            $invoice['gross_amount'] = $invoice['ff_amount'] + $invoice['extra_amount'];
            $invoice['discount_type'] = $discountType;
            if ($invoice['service_id'] == 4 || $invoice['service_id'] == 5 || $invoice['service_id'] == 7) {
                $invoice['discount_amount'] = round(($invoice['gross_amount'] * $discount) / 100, 2);
            }
            $invoice['net_amount'] = $invoice['gross_amount'] - $invoice['discount_amount'];
            $invoice['card_surcharge'] = $surcharge;
            $invoice['surcharge_amount'] = round(($surcharge * $invoice['net_amount']) / 100, 2);
            $invoice['gst_amount'] = round(($invoice['net_amount'] * 10) / 100, 2);
            $invoice['paid_amount'] = round($invoice['net_amount'] + $invoice['surcharge_amount'] + $invoice['gst_amount'], 2);

            return $invoice;
        } catch (\Exception $e) {
            app('log')->error("Grand Calculation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get calculation detail.', ['error' => 'Could not get calculation detail.']);
        }
    }

    public static function grandUnitCalc($invoice, $totalTimesheetUnit, $carryUnit, $fixedUnit, $ExtraUnit) {
        try {
            $invoice['timesheet_unit'] = $totalTimesheetUnit;
            $invoice['carry_unit'] = $carryUnit;
            $invoice['fixed_unit'] = $fixedUnit;
            $invoice['extra_unit'] = $ExtraUnit;
            $invoice['woff_unit'] = max(round($totalTimesheetUnit - ($carryUnit + $fixedUnit + $ExtraUnit)), 0);
            $invoice['won_unit'] = max(round(($carryUnit + $fixedUnit + $ExtraUnit) - $totalTimesheetUnit), 0);
            return $invoice;
        } catch (\Exception $e) {
            app('log')->error("Grand Calculation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get calculation detail.', ['error' => 'Could not get calculation detail.']);
        }
    }

    public function subActivityCalculationShow($subActivityTotal, $subactivityBillingDetail, $subActivityCodes) {
        try {
            $subactivityShow = array();
            $i = 0;
            foreach ($subActivityTotal as $code => $val) {
                $subactivityShow[$val['master_id']][$val['task_name']][$i]['code'] = $code;
                $subactivityShow[$val['master_id']][$val['task_name']][$i]['totalValue'] = $val['total'];
                $subactivityShow[$val['master_id']][$val['task_name']][$i]['fixed'] = 0;
                if (isset($subactivityBillingDetail['subActivity'][$code])) {
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['fixed'] = isset($subactivityBillingDetail['fixedValue'][$code]) ? $subactivityBillingDetail['fixedValue'][$code] : '0';
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['extra'] = max($val['total'] - $subactivityShow[$val['master_id']][$val['task_name']][$i]['fixed'], 0);
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['price'] = $subactivityBillingDetail['subActivity'][$code]['price'];
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['min'] = $subactivityBillingDetail['subActivity'][$code]['fixed_fee'];
                } else {
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['extra'] = 0;
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['price'] = 0;
                    $subactivityShow[$val['master_id']][$val['task_name']][$i]['min'] = 0;
                }
                $i++;
            }
            return $subactivityShow;
        } catch (\Exception $e) {
            app('log')->error("Subactivity Calculation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Subactivity calculation detail.', ['error' => 'Could not get Subactivity calculation detail.']);
        }
    }

}

?>