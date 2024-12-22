<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class BillingServicesSubactivity extends Model {

    protected $table = 'billing_subactivity';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = [];

    //for get billing subactivity detail for invoice calculation
    public static function getEntityBillingSubactivity($created_on, $billing_id, $fixedNoEmpSubActivity) {
        $billingSubactivity = BillingServicesSubactivity::leftjoin("subactivity as s", "s.subactivity_code", "billing_subactivity.subactivity_code")
                ->select("billing_subactivity.*", "s.master_id", "s.task_id")
                ->where("billing_subactivity.billing_id", $billing_id);

        $billingSubactivity = $billingSubactivity->where("billing_subactivity.created_on", "<=", $created_on);

        $billingSubactivity = $billingSubactivity->get();

        $billinsubActivityDetail = $fixedNoEmp = $billingSubactivityArray = array();
        if (!empty($billingSubactivity)) {
            foreach ($billingSubactivity as $row) {
                $billingSubactivityArray[$row->subactivity_code] = $row;
                if ($row->inc_in_ff == 1 && in_array($row->subactivity_code, $fixedNoEmpSubActivity)) { // this is for get fixed no of value
                    if($row->subactivity_code =='404'){
                    $fixedNoEmp[$row->subactivity_code] = $row->no_of_value;
                    }else{
                        $fixedNoEmp[$row->subactivity_code] = $row->fixed_value; 
                    }
                }
            }
            $billinsubActivityDetail = array('subActivity' => $billingSubactivityArray, 'fixedValue' => $fixedNoEmp);
        }
        return $billinsubActivityDetail;
    }

    public static function addSubactivity($entityId, $serviceId, $billingId, $masterId, $calcId, $oldBillingid) {
        if ($serviceId == 1) {
            $masterIds = implode(",", $masterId);
            $ffSubactivity = SubActivity::leftjoin("billing_subactivity as bs", function($join) use ($entityId) {
                                $join->on("bs.subactivity_code", "=", "subactivity.subactivity_code");
                                $join->on("bs.is_latest", "=", DB::raw('1'));
                                $join->on("bs.service_id", "=", DB::raw('1'));
                                $join->on("bs.entity_id", "=", DB::raw($entityId));
                            })
                            ->select("subactivity.subactivity_code", "bs.id", "bs.inc_in_ff", "bs.frequency_id", "bs.fixed_fee", "bs.price", "bs.fixed_value", "bs.no_of_value")
                            ->where("subactivity.visible", "1")
                            ->whereRaw("subactivity.master_id IN (" . $masterIds . ")")->get();
        } else {
            if ($oldBillingid == 0) {
                $ffSubactivity = BillingPayrollSubactivity::where("billing_payroll_subactivity.calc_id", $calcId)->get();
            } else {
                $ffSubactivity = BillingServicesSubactivity::where("entity_id", $entityId)
                                ->where("service_id", "2")->where("is_latest", "1")->get();
            }
        }
        //showArray($ffSubactivity);exit;
        if ($oldBillingid == 0) {
            BillingServicesSubactivity::where("entity_id", $entityId)
                    ->where("billing_id", $billingId)
                    ->where("service_id", $serviceId)
                    ->update(["is_latest" => "0"]);
        }
        foreach ($ffSubactivity as $row) {
            $fixedFee = $price = '0.00';
                if($row->subactivity_code ==2002){
                    $fixedFee = 52;
                }
                if($row->subactivity_code ==2102){
                    $fixedFee = 28;
                }
                if($row->subactivity_code ==9){
                    $price = 40;
                }
                if($row->subactivity_code ==148){
                    $price = 28;
                }
                if($row->subactivity_code ==1230){
                    $price = 45;
                }
            $billingSubactivity[] = array(
                'billing_id' => $billingId,
                'entity_id' => $entityId,
                'service_id' => $serviceId,
                'subactivity_code' => $row->subactivity_code,
                'inc_in_ff' => isset($row->inc_in_ff) ? $row->inc_in_ff : '0',
                'frequency_id' => isset($row->frequency_id) ? $row->frequency_id : '0',
                'fixed_fee' => isset($row->fixed_fee) ? $row->fixed_fee : $fixedFee,
                'price' => isset($row->price) ? $row->price : $price,
                'fixed_value' => isset($row->fixed_value) ? $row->fixed_value : '0',
                'no_of_value' => isset($row->no_of_value) ? $row->no_of_value : '0',
                'is_latest' => 1,
                'created_on' => date("Y-m-d H:i:s"),
                'created_by' => loginUser());
            
        }
        //showArray($billingSubactivity);exit;
        BillingServicesSubactivity::insert($billingSubactivity);
        if ($oldBillingid != 0) {
            //update old valuewith zero
            BillingServicesSubactivity::where("entity_id", $entityId)
                    ->where("billing_id", $oldBillingid)
                    ->where("service_id", $serviceId)
                    ->update(["is_latest" => "0"]);
        }
    }

    public static function saveAudit($diffArray) {
        //showArray($diffArray);exit;
        $colname = [
            'frequency_id' => 'Frequency',
            'fixed_fee' => 'Fixed Fee',
            'inc_in_ff' => 'Inc in FF',
            'subactivity_code' => 'subactivity code',
            'fixed_value' => 'fixed value',
            'no_of_value' => 'no of value',
        ];
        $changesArray = array();
        foreach ($diffArray as $key => $value) {
            $oldValue = $value[0];
            $value = $value[1];
            $colname = isset($colname[$key]) ? $colname[$key] : $key;
            if ($key == 'frequency_id') {
                $frequency = \App\Models\Backend\Frequency::where("is_active", "1")->get()->pluck("frequency_name", "id")->toArray();
                $oldval = ($oldValue != '') ? $frequency[$oldValue] : '';
                $newval = ($value != '') ? $frequency[$value] : '';
                $changesArray[$key] = [
                    'display_name' => ucfirst($colname),
                    'old_value' => $oldval,
                    'new_value' => $newval,
                ];
            }else if ($key == 'inc_in_ff') {                
                 $changesArray[$key] = [
                    'display_name' => ucfirst($colname),
                    'old_value' => ($oldValue ==1) ? 'Yes':'No',
                    'new_value' => ($value==1) ? 'Yes':'No'];
            } else {
                $changesArray[$key] = [
                    'display_name' => ucfirst($colname),
                    'old_value' => $oldValue,
                    'new_value' => $value];
            }
        }
        return $changesArray;
        //showArray(json_encode($changesArray));exit;
        //Insert value in audit table
    }

    public static function saveHistory($changesArray, $entityId, $serviceId) {
        if(!empty($changesArray)){
        BillingServicesSubactivityAudit::create([
            'entity_id' => $entityId,
            'service_id' => $serviceId,
            'changes' => json_encode($changesArray),
            'modified_on' => date('Y-m-d H:i:s'),
            'modified_by' => loginUser()
        ]);
        }
    }

    public static function getSubactivityReportData() {
        return BillingServicesSubactivity::leftjoin("entity as e", "e.id", "billing_subactivity.entity_id")
                ->leftjoin("subactivity as s","s.subactivity_code","billing_subactivity.subactivity_code")
                        ->leftjoin("entity as ep","ep.id","e.parent_id")
                        ->leftJoin('entity_allocation as ea', function($query) {
                            $query->on('ea.entity_id', '=', 'billing_subactivity.entity_id');
                            $query->on('ea.service_id', '=', 'billing_subactivity.service_id');
                        })
                        ->where("billing_subactivity.is_latest", "1")
                        ->where("e.discontinue_stage", "!=", "2");
    }

    public static function reportArrangeData($data) {
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();

        $designationids = Designation::select("designation_name")->where("is_display_in_allocation", "1")->get();
        foreach ($designationids as $designation) {
            $arrDDOption[$designation->designation_name] = $user;
        }
        $arrDDOption['Service'] = Services::where('is_active', '=', '1')->get()->pluck('service_name', 'id')->toArray();
        $arrDDOption['Frequency'] = Frequency::where('is_active', '=', '1')->get()->pluck('frequency_name', 'id')->toArray();
        $arrDDOption['Inc In FF'] = config('constant.yesNo');
        $arrDDOption['Subactivity'] = SubActivity::where('is_active', '=', '1')->get()->pluck('subactivity_full_name', 'subactivity_code')->toArray();
        foreach ($data->toArray() as $key => $value) {
            foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;
            }
        }

        return $data;
    }

}
