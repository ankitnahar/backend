<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityOtherInfo extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    protected $table = 'entity_other_info';
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
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function otherAccountId() {
        return $this->belongsTo(OtherAccount::class, 'otheraccount_id', 'id');
    }

    public static function OtherInformationData($entity_id) {        
        return EntityOtherInfo::with('createdBy:userfullname as created_by,id', 'otherAccountId:account_name,id')
                        ->where('entity_other_info.entity_id', $entity_id);
    }

    public static function boot() {
        parent::boot();
        self::updating(function($otherInfo) {
            $col_name = [
                'otheraccount_id' => 'Other Account Name',
                'view_access' => 'Viewing rights',
                'befree_comment' => 'Befree Comment',
                'internal_comment' => 'Internal Comment',
                'is_active' => 'is active'
            ];
            $changesArray = \App\Http\Controllers\Backend\Entity\OtherInformationController::saveHistory($otherInfo, $col_name);

            $updatedBy = loginUser();
            //Insert value in audit table
            EntityOtherInfoAudit::create([
                'entity_id' => $otherInfo->entity_id,
                'other_account_id' => $otherInfo->id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => $updatedBy
            ]);
        });
    }

}
