<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueEntityAudit extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'discontinue_entity_audit';
    protected $hidden = [];
    public $timestamps = false;
    
    function modifiedBy(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function arrangeData($data){
        $stage = DiscontinueStage::pluck('stage', 'id')->toArray();
        $status = DiscontinueStatus::pluck('status', 'id')->toArray();
        $i=0;
        foreach($data as $key => $value){
            if($value->log_type == 0)
                $name = $stage[$value->values];
            else
                $name = $status[$value->values];
                    
            $data[$i]->values = $name;
            $i++;
        }
        return $data;
    }
}
