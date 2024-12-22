<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    protected $table = 'entity';
    public $timestamps = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the user related to the client
     *
     * @return mixed
     */
    public function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function feedbackAssignee() {
        return $this->belongsTo(\App\Models\User::class, 'feedback_assignee', 'id');
    }
    
    public function parentEntity() {
        return $this->belongsTo(Entity::class, 'parent_id', 'id');
    }

    /*
     * Save history when entity basic information update 
     */

    public static function boot() {
        parent::boot();
        self::updating(function($entity) {
            $col_name = [
                'name' => 'Legal name',
                'billing_name' => 'Billing name',
                'trading_name' => 'Trading name',
                'related_entity' => 'Related entity',
                'related_entity_id' => 'Name of related entity',
                'abn_number' => 'ABN Number',
                'abn_branch_code' => 'ABN Branch Code',
                'abn_register_date' => 'Date from when client is registered for ABN',
                'tfn_number' => 'TFN number',
                'business_type' => 'Type of business',
                'entity_type' => 'Type Of entity',
                'bk_doneby' => 'Book keeping done by',
                'gst_register' => 'Registered for GST',
                'gst_register_date' => 'Date from which client is registered for GST',
                'bas_frequency' => 'BAS Frequency',
                'bas_accrualorcash' => 'BAS is on accrual or cash',
                'payg_frequency' => 'PAYG Frequency',
                'financial_institution_updateon_ato' => 'Financial Institution detail updated on ATO',
                'statement_delivery_preference' => 'Activity statement delivery preference',
                'entity_registerfor_fbt' => 'Is this client registered for FBT',
                'entity_registerfor_fueltaxcredit' => 'Is this client registered for Fuel Tax Credit',
                'group_client_belongsto' => 'Group client belongs to',
                'franchise' => 'Franchise',
                'website' => 'Website',
                'contract_signed_date' => 'Contract Signed Date',
                'entity_writeoff' => 'Client writeoff',
                'reviewer_budgeted_unit' => 'Reviewer budgeted unit',
                'dynamic_json' => 'Dynamic fields',
                'version_notes' => 'Version notes',
                'software_notes' => 'Software notes',
                'ap_notes' => 'AP notes',
                'ar_notes' => 'AR notes',
                'bk_notes' => 'BK notes',
                'bk_review_notes' => 'BK review notes',
                'dm_notes' => 'DM notes',
                'payroll_notes' => 'Payroll notes',
                'tax_notes' => 'Tax note'
            ];

            $changesArray = \App\Http\Controllers\Backend\Entity\EntityController::saveHistory($entity, $col_name);
            if (!empty($changesArray)) {
                $tab = $changesArray['tab_id'];

                //Insert value in audit table
                EntityAudit::create([
                    'entity_id' => $entity->id,
                    'tab' => $tab,
                    'changes' => json_encode($changesArray['changes']),
                    'modified_by' => app('auth')->guard()->id(),
                    'modified_on' => date('Y-m-d H:i:s')
                ]);
            }
        });
    }

    /*
     * Created by - Jayesh Shingrakhiya
     * Display entity tabs
     */

    public static function entityTab($id) {
        $tabs = Dynamicgroup::select('dynamic_group.id', 'dynamic_group.group_name', 's.parent_id', 's.id as service_id', 'bs.id as billingId')
                        ->leftJoin('billing_services as bs', 'bs.service_id', 'dynamic_group.service_id')
                        ->leftJoin('services as s', 's.id', 'bs.service_id')
                        ->where('bs.entity_id', $id)
                        ->where('bs.is_latest', 1)->where('bs.is_active', 1)->get()->toArray();

        $arrangeData = array();
//        foreach ($tabs as $key => $value) {
//            if ($value['parent_id'] == 1)
//                $childData[] = $value;
//        }
        if (!empty($tabs)) {
            foreach ($tabs as $value) {
                if ($value['service_id'] == 1) {
                    //$billingBkRatePerHour = BillingBKRPH::select('dg.id', 'dg.group_name', 'billing_bk_rph.service_id')->leftJoin('dynamic_group as dg', 'dg.service_id', 'billing_bk_rph.service_id')->leftJoin('services as s', 's.id', 'billing_bk_rph.service_id')->where('billing_bk_rph.service_id', '!=', 11)->where('billing_id', $value['billingId'])->where('inc_in_ff', 1)->where('is_latest', 1)->get()->toArray();
                    $billingBkRatePerHour = BillingBKRPH::select('dg.id', 'dg.group_name', 'billing_bk_rph.service_id')->leftJoin('dynamic_group as dg', 'dg.service_id', 'billing_bk_rph.service_id')->leftJoin('services as s', 's.id', 'billing_bk_rph.service_id')->where('billing_id', $value['billingId'])->where('billing_bk_rph.service_id', '!=', 11)->where('contract_signed_date', "!=", '0000-00-00')->where('is_latest', 1)->get()->toArray();

                    $childData = array();
                    foreach ($billingBkRatePerHour as $billingKey => $billingValue) {
                        $data = array();
                        $data['id'] = $billingValue['id'];
                        $data['group_name'] = $billingValue['group_name'];
                        $data['parent_id'] = $billingValue['service_id'];
                        $data['service_id'] = $billingValue['service_id'];
                        $childData[] = $data;
                    }
                    $value['child'] = $childData;
                    $arrangeData[] = $value;
                } else {
                    if ($value['parent_id'] == 0) {
                        $arrangeData[] = $value;
                    }
                }
            }
        }
        return $arrangeData;
    }

    /*
     * Created by - Jayesh Shingrakhiya
     * Display entity agreed service
     */

    public static function entityService($id) {
//        $tabs = BillingServices::select('service_name', 'service_id', 's.parent_id')
//                        ->leftJoin('services as s', 's.id', 'service_id')
//                        ->where('entity_id', $id)
//                        ->where('is_latest', 1)->get()->toArray();
//
//        $childData = $arrangeData = array();
//        foreach ($tabs as $key => $value) {
//            if ($value['parent_id'] == 1)
//                $childData[] = $value;
//        }
//
//        foreach ($tabs as $value) {
//            if ($value['service_id'] == 1) {
//                $value['child'] = $childData;
//                $arrangeData[] = $value;
//            } else {
//                if ($value['parent_id'] == 0) {
//                    $arrangeData[] = $value;
//                }
//            }
//        }
//        return $arrangeData;

        /* $tabs = Dynamicgroup::select('dynamic_group.id', 'dynamic_group.group_name as service_name', 's.parent_id', 's.id as service_id', 'bs.id as billingId')
          ->leftJoin('billing_services as bs', 'bs.service_id', 'dynamic_group.service_id')
          ->leftJoin('services as s', 's.id', 'bs.service_id')
          ->where('bs.entity_id', $id)
          ->where('bs.is_latest', 1)->get()->toArray(); */

        $tabs = Dynamicgroup::select('dynamic_group.id', 'dynamic_group.group_name as service_name', 's.parent_id', 's.id as service_id', 'bs.id as billingId')
                        ->leftJoin('billing_services as bs', 'bs.service_id', 'dynamic_group.service_id')
                        ->leftJoin('services as s', 's.id', 'bs.service_id')
                        ->where('bs.entity_id', $id)
                        ->where('bs.is_latest', 1)
                        ->where('bs.is_active', 1)->get()->toArray();

        $arrangeData = array();
//        foreach ($tabs as $key => $value) {
//            if ($value['parent_id'] == 1)
//                $childData[] = $value;
//        }
        if (!empty($tabs)) {
            foreach ($tabs as $value) {
                if ($value['service_id'] == 1) {
                    //$billingBkRatePerHour = BillingBKRPH::select('dg.id', 'dg.service_id', 'dg.group_name as service_name', 'billing_bk_rph.service_id')->leftJoin('dynamic_group as dg', 'dg.service_id', 'billing_bk_rph.service_id')->leftJoin('services as s', 's.id', 'billing_bk_rph.service_id')->where('billing_id', $value['billingId'])->where('billing_bk_rph.service_id', '!=', 11)->where('inc_in_ff', 1)->where('is_latest', 1)->get()->toArray();
                    $billingBkRatePerHour = BillingBKRPH::select('dg.id', 'dg.service_id', 'dg.group_name as service_name', 'billing_bk_rph.service_id')->leftJoin('dynamic_group as dg', 'dg.service_id', 'billing_bk_rph.service_id')->leftJoin('services as s', 's.id', 'billing_bk_rph.service_id')->where('billing_id', $value['billingId'])->where('billing_bk_rph.service_id', '!=', 11)->where('contract_signed_date', "!=", '0000-00-00')->where('is_latest', 1)->get()->toArray();

                    $childData = array();
                    foreach ($billingBkRatePerHour as $billingKey => $billingValue) {
                        $data = array();
                        $data['id'] = $billingValue['id'];
                        $data['service_name'] = $billingValue['service_name'];
                        $data['parent_id'] = $billingValue['service_id'];
                        $data['service_id'] = $billingValue['service_id'];
                        $childData[] = $data;
                    }
                    $value['child'] = $childData;
                    $arrangeData[] = $value;
                } else {
                    if ($value['parent_id'] == 0) {
                        $arrangeData[] = $value;
                    }
                }
            }
        }
        return $arrangeData;
    }

    /*
     * Created by - Jayesh Shingrakhiya
     * Display entity agreed service
     */

    public static function arrangeData($data) {
        $serviceAgreed = BillingServices::select('service_id', 'entity_id')->where('is_latest', 1)->groupBy('entity_id')->orderBy('service_id', 'asc')->get()->pluck('service_id', 'entity_id')->toArray();
        $documentUpload = Document::select('id', 'entity_id')->groupBy('entity_id')->get()->pluck('entity_id', 'entity_id')->toArray();

        $i = 0;
        foreach ($data as $key => $value) {
            $data[$i]->is_service = isset($serviceAgreed[$data[$i]->id]) ? $serviceAgreed[$data[$i]->id] : 0;
            $data[$i]->is_document = isset($documentUpload[$data[$i]->id]) ? 1 : 0;
            $data[$i]->module_id = config('constant.module.entity');
            $i++;
        }
        return $data;
    }

    /*
     * Create By Pankaj
     * Created On - 06-08-2018
     * for report
     */

    public static function reportArrangeData($data, $fields) {
        foreach ($fields as $field) {
            $arrDDOption[$field->field_title] = config('constant.' . $field->field_value);
        }
        $arrDDOption['Group client belongs to'] = EntityGroupclientBelongs::where('is_active', 1)->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Parent Entity Name'] = Entity::where('discontinue_stage', "!=",2)->get()->pluck('trading_name', 'id')->toArray();
        foreach ($data->toArray() as $key => $value) {
            foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;
            }
        }

        return $data;
    }

}
