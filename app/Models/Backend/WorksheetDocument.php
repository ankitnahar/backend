<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetDocument extends Model {

    protected $guarded = [];
    protected $table = 'worksheet_document';
    public $timestamps = false;

    function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    function deleted_by() {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by', 'id');
    }

    public static function arrangeData($data) {
        $document = array();
        foreach ($data as $key => $value) {
            if ($value->document_type == 1)
                $document['client_doc'][] = $value;
            else
                $document['internal_doc'][] = $value;
        }
        return $document;
    }

}
