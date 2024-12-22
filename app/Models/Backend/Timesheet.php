<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Timesheet extends Model {

    protected $guarded = [];
    protected $table = 'timesheet';
    protected $hidden = [];
    public $timestamps = false;

    public function assignee() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
    
    public static function timesheetData() {
        return Timesheet::
                        leftjoin("entity as e", "e.id", "timesheet.entity_id")
                        ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                        ->leftJoin('user as u', 'u.id', '=', 'timesheet.user_id')
                        ->leftJoin('master_activity as m', 'm.id', '=', 'w.master_activity_id')
                        ->leftJoin('task as t', 't.id', '=', 'w.task_id')
                        ->leftJoin('subactivity as s', 's.subactivity_code', '=', 'timesheet.subactivity_code')
                        ->leftjoin('frequency as f', 'f.id', '=', 'timesheet.frequency_id')
                        ->select(['timesheet.id', 'u.user_bio_id', 'u.userfullname', 'u.id as user_id', 'e.name as entity_name', 'e.id as entity_id', 'm.name as master', 'm.id as master_id', 't.name as task', 't.id as task_id', 's.subactivity_full_name', 's.id as subactivity_full_id', 'w.start_date', 'w.end_date', 'f.frequency_name', 'f.id as frequency_id', 'timesheet.*'])
                        ->where("e.discontinue_stage","!=","2");
    }

    public static function getInvoiceTimesheet($invoice,$masterIds = null) {
        $timesheet = \App\Models\Backend\Timesheet::with("assignee:id,userfullname")
                ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->leftjoin("master_activity as m", "m.id", "w.master_activity_id")
                ->leftjoin("task as t", "t.id", "w.task_id")
                ->leftjoin("subactivity as s", "s.subactivity_code", "timesheet.subactivity_code")
                ->leftjoin("frequency as f", "f.id", "timesheet.frequency_id")
        
//                ->select('w.id as worksheet_id', 'w.master_activity_id', 'm.name as master_name', 'w.start_date', 'w.end_date', 't.name as task_name', 't.id as task_id','s.subactivity_name', 'f.frequency_name', 
//                        'timesheet.user_id', 'timesheet.date', 'timesheet.units', 'timesheet.notes', 'timesheet.id', 's.subactivity_code', 'timesheet.bank_cc_name', 'timesheet.bank_cc_account_no', 
//                        'timesheet.no_of_value', 'timesheet.extra_value','timesheet.name_of_employee', 's.subactivity_code', 's.invoice_desc', 's.id as subactivity_id', 'timesheet.billing_status', 
//                        's.ff_rule', 's.not_ff_rule', 's.chargeable', 's.visible', 'w.frequency_id', 'w.task_id', 'timesheet.period_startdate', 'timesheet.period_enddate', 'timesheet.invoice_amt', 
//                        'timesheet.reviewer_id', 'timesheet.payroll_option_id', 'timesheet.payroll_option_id','timesheet.reviewer_id','timesheet.carry_forward_invoice_ids')
//                ->where("m.service_id",$invoice->service_id)
//                ->where("timesheet.user_id","!=","")

                ->select('w.id as worksheet_id', 'w.master_activity_id', 'm.name as master_name', 'w.start_date', 'w.end_date', 't.name as task_name', 't.id as task_id', 's.subactivity_name', 'f.frequency_name', 'timesheet.user_id', 'timesheet.date', 'timesheet.units', 'timesheet.notes', 'timesheet.id', 's.subactivity_code', 'timesheet.bank_cc_name', 'timesheet.bank_cc_account_no', 'timesheet.no_of_value', 'timesheet.extra_value', 'timesheet.name_of_employee', 's.subactivity_code', 's.invoice_desc', 's.id as subactivity_id', 'timesheet.billing_status', 's.ff_rule', 's.not_ff_rule', 's.chargeable', 's.visible', 'w.frequency_id', 'w.task_id', 'timesheet.period_startdate', 'timesheet.period_enddate', 'timesheet.invoice_amt', 'timesheet.reviewer_id', 'timesheet.payroll_option_id')
                ->where("m.service_id", $invoice->service_id)
                ->where("timesheet.entity_id", $invoice->entity_id);
        if($masterIds != null){
            $timesheet = $timesheet->whereRaw("m.id IN ($masterIds)");
        }
        if ($invoice->status_id == '1' || $invoice->status_id == '2' || $invoice->status_id == '6' || $invoice->status_id == '10') {
            $condition = " ((timesheet.date >= '" . $invoice->from_period . "' AND timesheet.date <= '" . $invoice->to_period . "' AND (timesheet.invoice_id =0 OR timesheet.invoice_id IS NULL))
                 OR ((timesheet.billing_status = 1 OR timesheet.billing_status = 3) AND timesheet.invoice_id =$invoice->id)
                 OR (timesheet.billing_status = 2 AND timesheet.date < '" . $invoice->to_period . "')) ";
        } else {
            $condition = " (timesheet.invoice_id =" . $invoice->id . " OR FIND_IN_SET($invoice->id,timesheet.carry_forward_invoice_ids))";
        }
        return $timesheet = $timesheet->whereRaw($condition)->orderBy("s.subactivity_code", "asc");
    }

    public static function getInvoicePreviewTimesheet($invoice, $type) {
        $timesheet = \App\Models\Backend\Timesheet::leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->leftjoin("master_activity as m", "m.id", "w.master_activity_id")
                ->leftjoin("task as t", "t.id", "w.task_id")
                ->leftjoin("subactivity as s", "s.subactivity_code", "timesheet.subactivity_code")
                ->leftjoin("frequency as f", "f.id", "timesheet.frequency_id")
                ->select('w.id as worksheet_id', 'w.master_activity_id', 'm.name as master_name', 'w.start_date', 'w.end_date', 't.name as task_name', 't.id as task_id', 's.subactivity_name', 'f.frequency_name', 'timesheet.date', 'timesheet.units', 's.subactivity_code', 'timesheet.bank_cc_name', 'timesheet.bank_cc_account_no', DB::raw("SUM(timesheet.no_of_value) as no_of_value"), DB::raw("SUM(timesheet.extra_value) as extra_value"), DB::raw("REPLACE(GROUP_CONCAT(timesheet.name_of_employee SEPARATOR '%SEP_ARRAY%'), ']%SEP_ARRAY%[', ',') AS name_of_employee, group_concat(timesheet.payroll_option_id) as payroll_option_id"), 's.invoice_desc', 's.id as subactivity_id', 'timesheet.billing_status', 'w.frequency_id', 'w.task_id', 'timesheet.period_startdate', 'timesheet.period_enddate', DB::raw("SUM(timesheet.invoice_amt) as invoice_amt"), 'timesheet.reviewer_id', 'timesheet.payroll_option_id')
                ->where("m.service_id", $invoice->service_id)
                ->where("s.invoice_desc", "!=", "")
                ->where("timesheet.entity_id", $invoice->entity_id)
                ->whereRaw("timesheet.invoice_id=$invoice->id and timesheet.billing_status = 1");
        /*if ($invoice->status_id == '1' || $invoice->status_id == '2' || $invoice->status_id == '6') {
            $condition = " ((timesheet.date >= '" . $invoice->from_period . "' AND timesheet.date <= '" . $invoice->to_period . "' AND timesheet.invoice_id =0)
                 OR ((timesheet.billing_status = 1 OR timesheet.billing_status = 3) AND timesheet.invoice_id =$invoice->id)
                 OR (timesheet.billing_status = 2 AND timesheet.date < '" . $invoice->to_period . "')) ";
        } else {
            $condition = " (timesheet.invoice_id =" . $invoice->id . " OR FIND_IN_SET($invoice->id,timesheet.carry_forward_invoice_ids))";
        }*/
        if ($type == '1') {
            $timesheet = $timesheet->groupBy("timesheet.subactivity_code", "timesheet.bank_cc_name","w.start_date", "w.end_date", "timesheet.bank_cc_account_no", "timesheet.period_startdate", "timesheet.period_enddate");
        } else if ($type == '2') {
            $timesheet = $timesheet->groupBy("timesheet.subactivity_code", "w.start_date", "w.end_date", "timesheet.period_startdate", "timesheet.period_enddate","timesheet.payroll_option_id");
        } else {
            $timesheet = $timesheet->groupBy("timesheet.subactivity_code");
        }

        return $timesheet;
    }

    //this is for calculate total unit in worksheet period 
    public static function bkSubactivityWipTotalUnit($invoiceid, $entityId, $fromPeriod, $toPeriod) {
        $subArray = array();
        $bkSubactivityTotalUnit = Timesheet::leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->select("timesheet.subactivity_code", "w.start_date", "w.end_date", DB::raw("SUM(units) as units"))
                ->where("timesheet.entity_id", "=", $entityId);
        $condition = " ((timesheet.date >= '" . $fromPeriod . "' AND timesheet.date <= '" . $toPeriod . "' AND timesheet.invoice_id =0)
                 OR ((timesheet.billing_status = 1 OR timesheet.billing_status = 3) AND timesheet.invoice_id =$invoiceid)
                 OR (timesheet.billing_status = 2 AND timesheet.date < '" . $toPeriod . "')) ";
        $bkSubactivityTotalUnit = $bkSubactivityTotalUnit->whereRaw($condition)
                        ->whereIn("timesheet.subactivity_code", ['2001', '2002', '2101', '2102', '709'])->groupBy("timesheet.subactivity_code", "w.start_date", "w.end_date");
        if ($bkSubactivityTotalUnit->count() > 0) {
            foreach ($bkSubactivityTotalUnit->get() as $row) {
                $subArray[$row->subactivity_code][$row->start_date . '-' . $row->end_date] = $row->units;
            }
        }
        return $subArray;
    }

    //this is for calculate total unit in worksheet period 
    public static function activityPeriod($invoiceid, $entityId, $fromPeriod, $toPeriod, $subactivityCodes) {
        $subArray = array();
        $subactivityCodes = implode(",", $subactivityCodes);
        $subactivity = Timesheet::leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->select("timesheet.subactivity_code", "w.start_date", "w.end_date", DB::raw("SUM(units) as units"))
                ->where("timesheet.entity_id", "=", $entityId);
        $condition = " ((timesheet.date >= '" . $fromPeriod . "' AND timesheet.date <= '" . $toPeriod . "' AND timesheet.invoice_id =0)
                 OR ((timesheet.billing_status = 1 OR timesheet.billing_status = 3) AND timesheet.invoice_id =$invoiceid)
                 OR (timesheet.billing_status = 2 AND timesheet.date < '" . $toPeriod . "')) ";
        $subactivity = $subactivity->whereRaw($condition)
                        ->whereRaw("timesheet.subactivity_code IN ($subactivityCodes)")->groupBy("timesheet.subactivity_code", "w.start_date", "w.end_date");
        return $subactivity;
    }

    //this is for calculate total unit in invoice 
    public static function subactivityTotalValue($invoiceid, $service_id, $entityId, $fromPeriod, $toPeriod, $subactivityCods) {
        $subArray = array();
        $subactivityCods = implode(",", $subactivityCods);
        $bkSubactivityTotalUnit = Timesheet::leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->leftjoin("master_activity as m", "m.id", "w.master_activity_id")
                ->leftjoin("task as t", "t.id", "w.task_id")
                ->select("timesheet.subactivity_code", "m.id as master_id", "t.name as task_name", "w.start_date", "w.end_date", DB::raw("SUM(no_of_value) as no_of_value"))
                ->where("timesheet.entity_id", "=", $entityId);
        $condition = " ((timesheet.date >= '" . $fromPeriod . "' AND timesheet.date <= '" . $toPeriod . "' AND timesheet.invoice_id =0)
                 OR ((timesheet.billing_status = 1) AND timesheet.invoice_id =$invoiceid)) ";
        $bkSubactivityTotalUnit = $bkSubactivityTotalUnit->whereRaw($condition)
                ->whereRaw("timesheet.subactivity_code IN ($subactivityCods)")
                ->where("m.service_id", $service_id)
                ->groupBy("timesheet.subactivity_code");

        if ($bkSubactivityTotalUnit->count() > 0) {
            foreach ($bkSubactivityTotalUnit->get() as $row) {
                $subArray[$row->subactivity_code] = array("master_id" => $row->master_id, "task_name" => $row->task_name, "total" => $row->no_of_value);
            }
        }
        return $subArray;
    }
    
    public static function getClientWiseTimesheetUnit(){
        return Timesheet::with("assignee:id,userfullname")
                ->leftjoin("entity as e","e.id","timesheet.entity_id")
                ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->leftjoin("master_activity as m", "m.id", "w.master_activity_id")
                ->leftjoin("task as t", "t.id", "w.task_id")
                ->leftjoin("subactivity as s", "s.subactivity_code", "timesheet.subactivity_code")
                ->leftjoin("frequency as f", "f.id", "timesheet.frequency_id")
                ->select("timesheet.user_id","e.code","e.name","e.trading_name","e.billing_name","e.discontinue_stage","w.start_date","w.end_date","m.name as master_name","t.name as task_name","s.subactivity_full_name",
                        "timesheet.date","timesheet.units","timesheet.notes","timesheet.billing_status","timesheet.period_startdate","timesheet.period_enddate","timesheet.no_of_value","timesheet.extra_value","timesheet.bank_cc_name","timesheet.bank_cc_account_no","f.frequency_name")
                ->whereRaw("timesheet.billing_status IN (0,2)")
                ->where("e.discontinue_stage","!=","2");
                
    }

    /* Created by: Jayesh Shingrakhiya, Sept 22, 2018, For fetch frequency name */
    public function frequencyId(){
        return $this->belongsTo(\App\Models\Backend\Frequency::class, 'frequency_id', 'id');
    }
    
    /* Created by: Jayesh Shingrakhiya, Sept 22, 2018, For fetch sub activity name */
    public function subactivityCode(){
        return $this->belongsTo(\App\Models\Backend\SubActivity::class, 'subactivity_code', 'subactivity_code');
    }
    
    /* Created by: Jayesh Shingrakhiya, Sept 22, 2018, For fetch bank name */
    public function payrollOptionId(){
        return $this->belongsTo(\App\Models\Backend\TimesheetPayrollOption::class, 'payroll_option_id', 'id');
    }
    
    /* Created by: Jayesh Shingrakhiya, Sept 22, 2018, For fetch reviewer name */
    public function reviewerId() {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id', 'id');
    }
}
