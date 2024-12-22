<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueQuestionAnswer extends Model {

    protected $table = 'discontinue_question_answer';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    function discontinueQuestionId() {
        return $this->belongsTo(\App\Models\Backend\DiscontinueQuestion::class, 'discontinue_question_id', 'id');
    }

    function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function arrangeData($data){
        $stage = DiscontinueStage::pluck('stage', 'id')->toArray();
        $result = array();
        foreach($data as $key => $value){
            $result[$stage[$value->who_fillup]][] = $value;
        }
        return $result;
    }
}