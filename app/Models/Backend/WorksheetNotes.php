<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetNotes extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'worksheet_notes';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function arrangeData($data) {
        $note = array();
        foreach ($data as $key => $value) {
            if ($value->type == 'P')
                $note['processingStaff'][] = $value;
            else if ($value->type == 'R')
                $note['reviewerStaff'][] = $value;
            else
                $note['tamStaff'][] = $value;
        }
        return $note;
    }
}
