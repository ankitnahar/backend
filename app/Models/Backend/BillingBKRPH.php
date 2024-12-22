<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingBKRPH extends Model {

    protected $table = 'billing_bk_rph';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public static function saveAudit($diffArray, $billingBKRPH, $entityId, $serviceId) {
        //showArray($diffArray);exit;
        $colname = [
            'service_name' => 'Service Name',
            'frequency_id' => 'Frequency',
            'fixed_fee' => 'Fixed Fee',
            'inc_in_ff' => 'Inc In FF',
            'contract_signed_date' => 'Service Start Date',
            'rph' => 'RPH'
        ];
        $ArrayYesNo = array('inc_in_ff');

        foreach ($diffArray as $key => $value) {
            if ($key == 'is_updated' || $key == 'is_latest') {
                continue;
            }
            if (is_array($value)) {
                $oldValue = $value[0];
                $value = $value[1];
                $colname = isset($colname[$key]) ? $colname[$key] : $key;
                if (in_array($key, $ArrayYesNo)) {
                    $oldval = ($oldValue == '1') ? 'Yes' : 'No';
                    $newval = ($value == '1') ? 'Yes' : 'No';
                    $oldValue = $oldval;
                    $value = $newval;
                } else if ($key == 'frequency_id') {
                    $frequency = \App\Models\Backend\Frequency::where("is_active", "1")->get()->pluck("frequency_name", "id")->toArray();
                    $oldval = ($oldValue != '') ? $frequency[$oldValue] : '';
                    $newval = ($value != '') ? $frequency[$value] : '';
                    $changesArray[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else {
                    $changesArray[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value];
                }
            }
        }
        //showArray(json_encode($changesArray));exit;
        //Insert value in audit table
        if (!empty($changesArray)) {
            BillingBKRPHAudit::create([
                'billing_id' => $billingBKRPH->billing_id,
                'service_id' => $billingBKRPH->service_id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => loginUser()
            ]);
        }
    }

}
