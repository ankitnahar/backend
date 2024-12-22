<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationDetail extends Model {

    protected $guarded = ['id'];

    protected $table = 'information_detail';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    /**
     * Get the documents for the blog post.
     */
    public function documents()
    {
        return $this->hasMany(\App\Models\Backend\InformationDetailDocument::class, 'information_detail_id', 'id');
    }
}
