<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceLog extends Model {

    protected $table = 'invoice_log';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function statusId() {
        return $this->belongsTo(InvoiceStatus::class, 'status_id', 'id');
    }
    
    public static function addLog($id,$status_id){
        $loginUser = loginUser();
        return $log = InvoiceLog::create([
                        'invoice_id' => $id,
                        'status_id' => $status_id,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser]
            );
    }

}
