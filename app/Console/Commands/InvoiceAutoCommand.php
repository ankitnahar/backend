<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class InvoiceAutoCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:auto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto invoice';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            //Awating invoice move to paid if amount zero
            $zeroInvoice = \App\Models\Backend\Invoice::where("status_id", "9")->where("paid_amount", "0.00")->where("parent_id","0")->get();
            foreach($zeroInvoice as $z){
            $amount = \App\Models\Backend\Invoice::where("invoice_no",$z->invoice_no)->sum("paid_amount"); 
            if($amount <= 0){
                $z->where("invoice_no",$z->invoice_no)->update(["status_id" => "4"]);
            }
            }
                    

            $today = date('Y-m-d');
            $recurringList = \App\Models\Backend\BillingServices::leftjoin("entity as e", "e.id", "billing_services.entity_id")
                    ->leftJoin('invoice_recurring as ir', function($query) {
                        $query->whereRaw("FIND_IN_SET(billing_services.entity_id,ir.entity_id)");
                        $query->on("ir.service_id", "billing_services.service_id");
                    })
                    ->leftjoin("billing_basic as b", "b.entity_id", "billing_services.entity_id")
                    ->leftjoin("invoice_recurring_detail as ird", "ird.recurring_id", "ir.id")
                    ->select("ird.start_date", "ird.end_date", "ird.invoice_date", "ir.*", "billing_services.entity_id", "billing_services.payroll_frequency_id", "e.name", "billing_services.fixed_total_unit", "billing_services.service_id", "billing_services.bk_in_ff", "billing_services.inc_in_ff", "billing_services.id as billing_id", "billing_services.fixed_fee", "billing_services.fixed_total_amount", "billing_services.auto_invoice", "billing_services.ff_rph", "billing_services.default_rph", "billing_services.frequency_id", 'billing_services.is_merge', 'b.contact_person', 'b.to_email', 'b.cc_email', 'b.address', 'b.full_time_resource', 'b.debtor_followup', 'b.merge_invoice', 'payment_id', 'b.ddr_rec', 'b.card_id', 'b.surcharge', 'b.card_number')
                    ->where("billing_services.is_active", "1")
                    ->where("ir.is_active", "=", "1")
                    ->where("b.parent_id", "=", "0")
                    ->where("billing_services.is_latest", "1")
                    ->where("ird.invoice_date", $today)
                    ->where("e.discontinue_stage", "!=", "2")
                    ->groupBy("billing_services.entity_id", "billing_services.service_id");
            // showArray(getSQL($recurringList));exit;
            if ($recurringList->get()->count() > 0) {
                foreach ($recurringList->get() as $recurring) {
                    try {
                        $checkInvoiceAlready = \App\Models\Backend\Invoice::where("entity_id", $recurring->entity_id)
                                ->whereRaw("DATE(created_on) = '$today'")
                                ->where("invoice_type", "Auto invoice")
                                ->where("service_id", $recurring->service_id);
                        if ($checkInvoiceAlready->count() == 0) {
                            if ($recurring->auto_invoice == 1) {
                                $discountType = 'None';
                                $discount = 0;
                                $description = array();
                                // BK PAyroll
                                if (in_array($recurring->service_id, array(1, 2))) {
                                    $invoice = \App\Models\Backend\Invoice::create([
                                                "entity_id" => $recurring->entity_id,
                                                "billing_id" => $recurring->billing_id,
                                                "status_id" => 2,
                                                "is_fixed_fees" => $recurring->inc_in_ff,
                                                "service_id" => $recurring->service_id,
                                                "from_period" => $recurring->start_date,
                                                "to_period" => $recurring->end_date,
                                                "invoice_type" => 'Auto invoice',
                                                "created_by" => 1,
                                                "created_on" => date('Y-m-d H:i:s')
                                    ]);
                                    $timesheetVal = $this->getTimesheet($invoice, $recurring);
                                    //exit;
                                    if ($invoice->service_id == 1) {
                                        $fixedUnit = $recurring->fixed_total_unit;
                                        $rph = $recurring->default_rph;
                                        $ff = $recurring->fixed_total_amount;
                                    } else {
                                        $rph = $recurring->ff_rph;
                                        $fixedUnit = ($recurring->fixed_fee > 0 ) ? round(($recurring->fixed_fee * ($recurring->ff_rph / 10))) : '0';
                                        $ff = $recurring->fixed_fee;
                                    }
                                    $extraUnit = 0;
                                    if ($rph > 0) {
                                        $extraUnit = ($timesheetVal['total_extra_amount'] > 0 ) ? round(($timesheetVal['total_extra_amount'] / ( $rph / 10)), 2) : '0';
                                    }
                                    //get total grand total unit
                                    $invoiceUnit = \App\Http\Controllers\Backend\Invoice\InvoiceWipController::grandUnitCalc($invoice, $timesheetVal['total_unit'], $timesheetVal['total_carry'], $fixedUnit, $extraUnit);
                                    $invoiceDetail = $this->grandTotalCalc($invoice, $ff, $timesheetVal['total_extra_amount'], $recurring->surcharge, $invoice->discount_type, $invoice->discount_amount, $recurring->service_id);

                                    //generate invoice No
                                    $extra_woff = max($invoiceUnit['timesheet_unit'] - ($invoiceUnit['fixed_unit'] + $invoiceUnit['extra_unit'] + $invoiceUnit['carry_unit']), 0);
                                    $extra_won = max(($invoiceUnit['fixed_unit'] + $invoiceUnit['extra_unit'] + $invoiceUnit['carry_unit']) - $invoiceUnit['timesheet_unit'], 0);
                                    $total_charge_unit = $invoiceUnit['fixed_unit'] + $invoiceUnit['extra_unit'];
                                    $invoiceUpdate = \App\Models\Backend\Invoice::where("id", $invoice->id)->update([
                                        'extra_woff' => $extra_woff,
                                        'extra_won' => $extra_won,
                                        'timesheet_unit' => $invoiceUnit['timesheet_unit'],
                                        'carry_unit' => $invoiceUnit['carry_unit'],
                                        'fixed_unit' => $invoiceUnit['fixed_unit'],
                                        'extra_unit' => $invoiceUnit['extra_unit'],
                                        'woff_unit' => $invoiceUnit['woff_unit'],
                                        'won_unit' => $invoiceUnit['won_unit'],
                                        'total_charge_unit' => $total_charge_unit,
                                        'ff_amount' => $invoiceDetail['ff_amount'],
                                        'extra_amount' => $invoiceDetail['extra_amount'],
                                        'gross_amount' => $invoiceDetail['gross_amount'],
                                        'discount_type' => $invoiceDetail['discount_type'],
                                        'discount_amount' => $invoiceDetail['discount_amount'],
                                        'net_amount' => $invoiceDetail['net_amount'],
                                        'card_surcharge' => $invoiceDetail['card_surcharge'],
                                        'surcharge_amount' => $invoiceDetail['surcharge_amount'],
                                        'gst_amount' => $invoiceDetail['gst_amount'],
                                        'paid_amount' => $invoiceDetail['paid_amount']
                                    ]);

                                    if ($recurring->service_id == 2 && $invoiceDetail['net_amount'] <= 0) {
                                        \App\Models\Backend\Invoice::where("id", $invoice->id)->update(["status_id" => 1, "invoice_type" => "Recurred"
                                        ]);
                                    }


                                    if ($recurring->service_id == 1) {
                                        $description[] = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::bkDescription($invoice, $recurring, $recurring->full_time_resource);
                                    } else {
                                        $description[] = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::payrollDescription($invoice, $recurring, $recurring->full_time_resource);
                                    }
                                    //showArray($description);exit;
                                    if (!empty($description)) {
                                        $this->addDescription($description, $invoice->id);
                                    }
                                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoice->id, 2);
                                } else if (in_array($recurring->service_id, array(7, 5, 4))) {
                                    // Subscription SMSF Hosting invoice
                                    $billingDetail = \App\Models\Backend\BillingServices::where("id", $recurring->billing_id)->first();
                                    if ($recurring->service_id == 7) {
                                        $amount = $billingDetail->standard_fee;
                                        if ($billingDetail->discount > 0 && $billingDetail->discount > '0.00') {
                                            $discountType = 'Fixed';
                                            $discount = $billingDetail->discount;
                                        }
                                        $description[] = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::
                                                subscriptionDescription($amount, $billingDetail);
                                    } else if ($recurring->service_id == 5) {
                                        $amount = \App\Models\Backend\BillingHostingUser::where("entity_id", $recurring->entity_id)->where("is_active", "1")->sum('rate');
                                        $description[] = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::
                                                hostingDescription($amount, $recurring);
                                    } else if ($recurring->service_id == 4) {
                                        $amount = $this->smsfInvoice($billingDetail, $recurring);
                                        $description[] = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::
                                                smsfDescription($amount);
                                    }

                                    //echo $discount;exit;
                                    $invoice = \App\Models\Backend\Invoice::create([
                                                "entity_id" => $recurring->entity_id,
                                                "billing_id" => $recurring->billing_id,
                                                "status_id" => 2,
                                                "is_fixed_fees" => $recurring->inc_in_ff,
                                                "service_id" => $recurring->service_id,
                                                "from_period" => $recurring->start_date,
                                                "to_period" => $recurring->end_date,
                                                "invoice_type" => 'Auto invoice',
                                                "created_by" => 1,
                                                "created_on" => date('Y-m-d H:i:s')
                                    ]);

                                    $invoiceDetail = $this->grandTotalCalc($invoice, $amount, 0, $recurring->surcharge, $discountType, $discount, $recurring->service_id);
                                    // showArray($invoiceDetail);exit;
                                    $invoiceUpdate = \App\Models\Backend\Invoice::where("id", $invoice->id)->update([
                                        'ff_amount' => $invoiceDetail['ff_amount'],
                                        'extra_amount' => $invoiceDetail['extra_amount'],
                                        'gross_amount' => $invoiceDetail['gross_amount'],
                                        'discount_type' => $invoiceDetail['discount_type'],
                                        'discount_amount' => $invoiceDetail['discount_amount'],
                                        'net_amount' => $invoiceDetail['net_amount'],
                                        'card_surcharge' => $invoiceDetail['card_surcharge'],
                                        'surcharge_amount' => $invoiceDetail['surcharge_amount'],
                                        'gst_amount' => $invoiceDetail['gst_amount'],
                                        'paid_amount' => $invoiceDetail['paid_amount']
                                    ]);

                                    if (!empty($description)) {
                                        $this->addDescription($description, $invoice->id);
                                    }
                                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoice->id, 11);
                                }
                                if ($recurring->service_id == 1 || $recurring->service_id == 2 || $recurring->service_id == 6) {
                                    $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $recurring->entity_id)
                                                    ->where("service_id", $recurring->service_id)->first();
                                    //update invoice hierarchy
                                    \App\Models\Backend\InvoiceUserHierarchy::create([
                                        'invoice_id' => $invoice->id,
                                        'user_hierarchy' => $entityAllocation->allocation_json
                                    ]);
                                }
                                unset($GLOBALS[301]);
                                unset($GLOBALS[2508]);
                                unset($GLOBALS[414]);
                                unset($GLOBALS[425]);
                                unset($GLOBALS[466]);
                            } else {

                                $statusId = 1;
                                if (in_array($recurring->service_id, array(7, 5, 4))) {
                                    $statusId = 2;
                                }
                                $invoice = \App\Models\Backend\Invoice::create([
                                            "entity_id" => $recurring->entity_id,
                                            "billing_id" => $recurring->billing_id,
                                            "status_id" => $statusId,
                                            "is_fixed_fees" => $recurring->inc_in_ff,
                                            "service_id" => $recurring->service_id,
                                            "from_period" => $recurring->start_date,
                                            "to_period" => $recurring->end_date,
                                            "invoice_type" => 'Recurred',
                                            "net_amount" => '0',
                                            "created_by" => 1,
                                            "created_on" => date('Y-m-d H:i:s')
                                ]);

                                //add log
                                \App\Models\Backend\InvoiceLog::addLog($invoice->id, $statusId);
                                if ($recurring->service_id == 1 || $recurring->service_id == 2 || $recurring->service_id == 6) {
                                    $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $recurring->entity_id)
                                                    ->where("service_id", $recurring->service_id)->first();

                                    //update invoice hierarchy
                                    \App\Models\Backend\InvoiceUserHierarchy::create([
                                        'invoice_id' => $invoice->id,
                                        'user_hierarchy' => $entityAllocation->allocation_json
                                    ]);
                                }

                                // add releated entity
                                $this->addRelated($recurring->entity_id, $recurring->service_id, $statusId, $invoice->id, $recurring->start_date, $recurring->end_date);
                            }
                        }
                    } catch (Exception $ex) {
                        $cronName = "Auto Invoice";
                        $message = $ex->getMessage();
                        cronNotWorking($cronName, $message);
                    }
                }
                $invoiceNo = $this->sendtoclientstageInvoice();
            }
        } catch (Exception $ex) {
            $cronName = "Auto Invoice";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

    public static function addRelated($entityId, $serviceId, $statusId, $id, $starDate, $endDate) {

        $relatedEntity = \App\Models\Backend\BillingServices::
                leftjoin("entity as e", "e.id", "billing_services.entity_id")
                ->leftjoin("billing_basic as b", "b.entity_id", "billing_services.entity_id")
                ->select("billing_services.inc_in_ff", "billing_services.entity_id", "billing_services.id as billing_id")
                ->where("billing_services.is_active", "1")
                ->where("billing_services.is_latest", "1")
                ->where("b.parent_id", $entityId)
                ->where("billing_services.service_id", $serviceId)
                ->where("e.discontinue_stage", "!=", "2");
        if ($relatedEntity->count() > 0) {
            foreach ($relatedEntity->get() as $related) {
                $invoice = \App\Models\Backend\Invoice::create([
                            "entity_id" => $related->entity_id,
                            "parent_id" => $id,
                            "billing_id" => $related->billing_id,
                            "status_id" => $statusId,
                            "is_fixed_fees" => $related->inc_in_ff,
                            "service_id" => $serviceId,
                            "from_period" => $starDate,
                            "to_period" => $endDate,
                            "invoice_type" => 'Recurred',
                            "net_amount" => '0',
                            "created_by" => 1,
                            "created_on" => date('Y-m-d H:i:s')
                ]);

                //add log
                \App\Models\Backend\InvoiceLog::addLog($invoice->id, $statusId);
                if ($serviceId == 1 || $serviceId == 2 || $serviceId == 6) {
                    $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $related->entity_id)
                                    ->where("service_id", $serviceId)->first();

                    //update invoice hierarchy
                    \App\Models\Backend\InvoiceUserHierarchy::create([
                        'invoice_id' => $invoice->id,
                        'user_hierarchy' => $entityAllocation->allocation_json
                    ]);
                }
            }
        }
    }

    /**
     * calculate smsf invoice amount
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function smsfInvoice($billing, $recurring) {
        try {
            $monthly = $billing->monthly_amount;
            $fixedffdate = $billing->ff_start_date;
            $invoicemonth = explode("-", $recurring->end_date);

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
                        $grossAmt = $monthly;
                } else
                    $grossAmt = $monthly;
            } else
                $grossAmt = $billing->fixed_fee;

            return $grossAmt;
        } catch (\Exception $e) {
            app('log')->channel('autoinvoice')->error("smsf gross amount calculation error : " . $e->getMessage());
        }
    }

    public function grandTotalCalc($invoice, $ff, $extraAmount, $surcharge, $discountType, $discount, $serviceId) {
        try {

            $invoice['ff_amount'] = $ff;
            $invoice['extra_amount'] = $extraAmount;
            $invoice['gross_amount'] = $invoice['ff_amount'] + $invoice['extra_amount'];
            ;
            $invoice['discount_type'] = $discountType;
            if ($serviceId == 4 || $serviceId == 5 || $serviceId == 7) {
                $invoice['discount_amount'] = round(($invoice['gross_amount'] * $discount) / 100, 2);
            }
            $invoice['net_amount'] = $invoice['gross_amount'] - $invoice['discount_amount'];
            $invoice['card_surcharge'] = $surcharge;
            $invoice['surcharge_amount'] = round(($surcharge * $invoice['net_amount']) / 100, 2);
            $invoice['gst_amount'] = round(($invoice['net_amount'] * 10) / 100, 2);
            $invoice['paid_amount'] = round($invoice['net_amount'] + $invoice['surcharge_amount'] + $invoice['gst_amount'], 2);

            return $invoice;
        } catch (\Exception $e) {
            app('log')->channel('autoinvoice')->error("Grand Calculation failed : " . $e->getMessage());
        }
    }

    /**
     * calculate timesheet on invoice period
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function getTimesheet($invoice, $billing) {
        try {
            //get all timesheet in between this invoice period and billing statusis not charge and carry forward
            // showArray($timesheetList->count());exit;
            if ($invoice->service_id == 1) {
                $masterId[] = '3,4,6,7,12,22,23,24,34';
                if ($billing->bk_in_ff == 1) {
                    $masterId[] .= '5';
                }
                $RPH['5'] = $RPH['6'] = $RPH['7'] = $RPH['22'] = $billing->ff_rph;
                $RPH['3'] = $RPH['4'] = $RPH['12'] = $RPH['23'] = $RPH['24'] = $RPH['34'] = $billing->default_rph;
                // echo $billing->billing_id;exit;
                $bkOther = \App\Models\Backend\BillingBKRPH::where("billing_id", $billing->billing_id)->get();

                foreach ($bkOther as $row) {
                    if ($row->inc_in_ff == 1) {
                        if ($row->service_id == '8') {
                            $masterId[] .= '9';
                            $ff[9] = $row->inc_in_ff;
                            $RPH[9] = $row->rph;
                        }
                        if ($row->service_id == '9') {
                            $masterId[] .= '10';
                            $ff[10] = $row->inc_in_ff;
                            $RPH[10] = $row->rph;
                        }
                        if ($row->service_id == '10') {
                            $masterId[] .= '11';
                            $ff[11] = $row->inc_in_ff;
                            $RPH[11] = $row->rph;
                        }
                        if ($row->payroll_ff_selected == '11') {
                            $masterId[] .= '8';
                            $ff[8] = $row->inc_in_ff;
                            $RPH[8] = $row->rph;
                        }
                    }
                }
                $masterIds = implode(",", $masterId);
            } else {
                $masterIds = '1,2';
                $RPH[1] = $billing->ff_rph;
                $RPH[2] = $billing->ff_rph;
            }
//showArray($masterIds);exit;
            $timesheetList = \App\Models\Backend\Timesheet::getInvoiceTimesheet($invoice, $masterIds);
            //assign variable
            $timesheetArray = $data = $totNoOfValue = array();
            $totalExtraAmount = $totalunit = $totalcarry = $totalwriteoff = 0;
            //show subactivity calculation
            if ($timesheetList->count() > 0) {
                // for bk subactivity calculation
                if ($invoice->service_id == 2) {
                    $payrollOption = \App\Models\Backend\TimesheetPayrollOption::get()->pluck("type_name", "id")->toArray();
                    //for subactivity 404,417 and 422 if inc in ff store fixed value
                    $subactivityCodeForValue = $fixedNoEmpSubActivity = array(301, 2508, 404, 417, 422, 463);
                    //get all vaisible subactivity detail
                    $subactivityDetail = \App\Models\Backend\BillingServicesSubactivity::getEntityBillingSubactivity($invoice->created_on, $billing->billing_id, $fixedNoEmpSubActivity);
                    //echo $billing->id;
                    //showArray($subactivityDetail);exit;
                    //if client is not on FF that time 404 and 417 take MIN value for no of employe
                    $GLOBALS['404'] = $GLOBALS['422'] = 1;

                    $subactivity = \App\Models\Backend\Timesheet::activityPeriod($invoice->id, $invoice->entity_id, $invoice->from_period, $invoice->to_period, $fixedNoEmpSubActivity);
                    //showArray($subactivity->get()->toArray());exit;
                    $subactivityDetail['fixedValue'][404] = 0;
                    if ($subactivity->count() > 0) {
                        $i = 0;
                        foreach ($subactivity->get() as $rowSub) {
                            if ($rowSub->subactivity_code == '404') {
                                $i = $i + 1;
                            }
                            $GLOBALS[$rowSub->start_date . '-' . $rowSub->end_date] = 1;
                        }
                        $subactivityDetail['fixedValue'][404] = $subactivityDetail['subActivity'][404]['no_of_value'] * $i;
                    }
                }

                $i = 0;
                foreach ($timesheetList->get() as $timesheet) {
                    $amount = 0;
                    if ($invoice->service_id == 1) {
                        $amount = 0;
                        //showArray($timesheet);exit;
                    } else if ($invoice->service_id == 2) {
                        if ($timesheet->visible == 1) {
                            /* for payroll
                             * if client is on FF and subactivity also is on FF that time we will not change FF entry other then we will charge all visible subactivity as per rule
                             * if client is not on FF that time we will charge all visible entry as per rule
                             */
                            //check client is on FF or not
                            $ff = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['inc_in_ff'] : '0';

                            if ($billing->inc_in_ff == 1) {
                                $sub['RPH'] = $billing->ff_rph;
                                $sub['units'] = $timesheet->units;
                                //check subactivity is on fixed fee or not
                                if ($ff == 1) {
                                    if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity)) {
                                        if ($timesheet->subactivity_code == 404) {
                                            $sub['fixed_value'] = $subactivityDetail['fixedValue'][404];
                                        } else {
                                            $sub['fixed_value'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'];
                                        }
                                        //$sub['fixed_value'] = $subactivityDetail['fixedValue'][$timesheet->subactivity_code];
                                        $sub['no_of_emp'] = $timesheet->no_of_value;
                                        $sub['RPH'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'];
                                        $sub['min'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'];
                                        $subactivityDetail['fixedValue'][$timesheet->subactivity_code] = max($subactivityDetail['fixedValue'][$timesheet->subactivity_code] - $timesheet->no_of_value, 0);
                                        $sub['wip_from_period'] = $timesheet->start_date;
                                        $sub['wip_to_period'] = $timesheet->end_date;
                                    }
                                    $subActivityRule = $timesheet->ff_rule;
                                } else {
                                    $subActivityRule = $timesheet->not_ff_rule;
                                    $sub['RPH'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['price']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'] : '0.00';
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
                                $sub['units'] = $timesheet->units;
                                $subActivityRule = $timesheet->not_ff_rule;
                                $sub['RPH'] = isset($subactivityDetail['subActivity'][$timesheet->subactivity_code]['price']) ? $subactivityDetail['subActivity'][$timesheet->subactivity_code]['price'] : '0.00';
                                $sub['no_of_emp'] = $timesheet->no_of_value;
                                if (in_array($timesheet->subactivity_code, $fixedNoEmpSubActivity)) {
                                    $sub['fixed_value'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'];
                                    $sub['min'] = $subactivityDetail['subActivity'][$timesheet->subactivity_code]['fixed_fee'];
                                    $sub['wip_from_period'] = $timesheet->start_date;
                                    $sub['wip_to_period'] = $timesheet->end_date;
                                    $subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] = max($subactivityDetail['subActivity'][$timesheet->subactivity_code]['no_of_value'] - $timesheet->no_of_value, 0);
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

                            $amount = \App\Http\Controllers\Backend\Invoice\InvoiceWipController::subactivityCalc($subActivityRule, $sub, $ff, $timesheet->subactivity_code);
                        } else {
                            $amount = 0;
                        }
                    }
                    if (!isset($masterArray[$timesheet->master_activity_id]['amount'])) {
                        $masterArray[$timesheet->master_activity_id]['master_activity_id'] = $timesheet->master_activity_id;
                        $masterArray[$timesheet->master_activity_id]['amount'] = $amount;
                        $masterArray[$timesheet->master_activity_id]['total_unit'] = $timesheet->units;
                    } else {
                        $masterArray[$timesheet->master_activity_id]['amount'] = $masterArray[$timesheet->master_activity_id]['amount'] + $amount;
                        $masterArray[$timesheet->master_activity_id]['total_unit'] = $masterArray[$timesheet->master_activity_id]['total_unit'] + $timesheet->units;
                    }
                    \App\Models\Backend\Timesheet::where("id", $timesheet->id)->update([
                        'invoice_amt' => $amount,
                        'invoice_id' => $invoice->id,
                        'billing_status' => "1"
                    ]);

                    if ($timesheet->billing_status == '0' || $timesheet->billing_status == '1') {
                        //calculate total unit
                        $totalunit = $totalunit + $timesheet->units;
                    } else if ($timesheet->billing_status == 2) {//calculate total carry unit
                        $totalcarry = $totalcarry + $timesheet->units;
                    } else {
                        $totalwriteoff = $totalwriteoff + $timesheet->units;
                    }

                    $totalExtraAmount = round($totalExtraAmount + $amount, 2);
                    $i++;
                }

                if (!empty($masterArray)) {

                    foreach ($masterArray as $key => $masterVal) {
                        $mData = array('invoice_id' => $invoice->id,
                            'master_id' => $masterVal['master_activity_id'],
                            'timesheet_unit' => $masterVal['total_unit'],
                            'woffunit' => 0,
                            'wonunit' => 0,
                            'woff_unit' => 0,
                            'carry_unit' => 0,
                            'charge_unit' => $masterVal['total_unit'],
                            'rate_per_hour' => $RPH[$masterVal['master_activity_id']],
                            'amount' => $masterVal['amount'],
                            'created_on' => date("Y-m-d h:i:s"),
                            'created_by' => loginUser());

                        $mid = \App\Models\Backend\InvoiceMasterUnitCalc::insert($mData);
                    }
                }
                return $totalCalculation = array('total_extra_amount' => $totalExtraAmount,
                    'total_unit' => $totalunit,
                    'total_carry' => $totalcarry);
            }
        } catch (\Exception $e) {
            app('log')->channel('autoinvoice')->error("amount Calculation failed : " . $e->getMessage());
        }
    }

    public static function sendtoclientstageInvoice() {
        $today = date('Y-m-d');
        $invoice = \App\Models\Backend\Invoice::where("invoice_type", "Auto invoice")
                        ->whereRaw("DATE(created_on) = '" . $today . "'")->where("status_id", "2");
        // showArray($invoice->count());exit;
        if ($invoice->count() > 0) {
            foreach ($invoice->get() as $invoice) {
                $invoiceAlready = \App\Models\Backend\Invoice::select("invoice_no")
                                ->where("invoice.invoice_type", "Auto invoice")
                                ->where("invoice.entity_id", $invoice->entity_id)
                                ->where("invoice.status_id", '11')->first();
                $is_merge = 0;
                if (isset($invoiceAlready->invoice_no)) {
                    $invoiceNo = $invoiceAlready->invoice_no;
                    $is_merge = 1;
                } else {
                    $invoiceNo = \App\Http\Controllers\Backend\Invoice\InvoicePreviewController::generateInvoiceNo($invoice->entity_id);
                }
                // echo $invoiceNo;exit;
                $invoiceUpdate = \App\Models\Backend\Invoice::where("id", $invoice->id)->update([
                    'status_id' => '11',
                    'invoice_no' => $invoiceNo
                ]);
                \App\Models\Backend\Invoice::where("invoice_no", $invoiceNo)->update(["is_merge" => $is_merge]);
                \App\Models\Backend\InvoiceDescription::where("invoice_id", $invoice->id)->update([
                    'invoice_no' => $invoiceNo
                ]);

                //add log
                $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoice->id, 11);
            }
        }
    }

    public static function addDescription($descriptions, $id) {
        $d = 0;
        foreach ($descriptions as $description) {
            foreach ($description as $row) {
                if ($row['description'] != '') {
                    $descriptionData = [
                        'invoice_id' => $id,
                        'inv_account_id' => isset($row['inv_account_id']) ? $row['inv_account_id'] : '',
                        'description' => $row['description'],
                        'amount' => isset($row['amount']) ? $row['amount'] : '',
                        'hide' => 0,
                        'sort_order' => $d,
                        'created_on' => date("Y-m-d h:i:s"),
                        'created_by' => loginUser()];

                    \App\Models\Backend\InvoiceDescription::Insert($descriptionData);
                    $d++;
                }
            }
        }
    }

}
