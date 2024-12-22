<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetAttentionQuestion extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'worksheet_attention_question';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function topicId() {
        return $this->belongsTo(\App\Models\Backend\WorksheetTraining::class, 'topic_id', 'id');
    }
}
