<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityBankInfo extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_bank_info';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function bankId() {
        return $this->belongsTo(Bank::class, 'bank_id', 'id');
    }

    public function TypeId() {
        return $this->belongsTo(Accounttype::class, 'type_id', 'id');
    }

    public static function BankInformationData($entity_id) {        
        return EntityBankInfo::with('createdBy:userfullname as created_by,id', 'bankId:bank_name,id', 'TypeId:type_name,id')
                        ->where('entity_bank_info.entity_id', $entity_id);
    }

    /*
     * Created by - Pankaj
     * save history when user information update 
     */

    public static function boot() {
        parent::boot();
        self::updating(function($bankInformation) {
            $col_name = [
                'bank_id' => 'bank name',
                'type_id' => 'type name',
                'is_bank_or_credit_card' => 'Bank Or Credit Card',
                'bsb_notes' => 'BSB notes',
                'account_no' => 'Account no',
                'bank_link' => 'Bank Link',
                'viewing_rights' => 'Viewing rights',
                'follow_up_notes' => 'Follow up notes',
                'auto_feed_up' => 'Auto feed up',
                'notes' => 'Notes',
                'is_active' => 'is active'
            ];
            $changesArray = \App\Http\Controllers\Backend\Entity\BankInformationController::saveHistory($bankInformation, $col_name);

            $updatedBy = loginUser();
            //Insert value in audit table
            EntityBankInfoAudit::create([
                'entity_id' => $bankInformation->entity_id,
                'bank_info_id' => $bankInformation->id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => $updatedBy
            ]);
        });
    }
    
    public static function getBankReportData() {
        return EntityBankInfo::leftjoin("entity as e","e.id","entity_bank_info.entity_id")
                ->leftjoin("entity as ep","ep.id","e.parent_id")
                ->where("e.discontinue_stage","!=","2");
    }
    
    
    public static function reportArrangeData($data) {
        $arrDDOption['Bank / CC / Paypal AC'] = config('constant.is_bank_or_credit_card');            
        $arrDDOption['Bank'] = Bank::getBank();
        $arrDDOption['Type of account'] = Accounttype::getAccountType();
        $arrDDOption['Is active ?'] = config('constant.yesNo');
        $arrDDOption['Bank link ?'] = config('constant.yesNoNa');
        $arrDDOption['Viewing rights ?'] = config('constant.yesNoNa');
        $arrDDOption['Auto feed ?'] = config('constant.yesNoNa');
        foreach ($data->toArray() as $key => $value) {     
             foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;   
            }            
        }     
        
        return $data;
    }

}
