<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QueryDetail extends Model {

    protected $guarded = [ ];

    protected $table = 'query_detail';
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
        return $this->hasMany(\App\Models\Backend\QueryDetailDocument::class, 'query_detail_id', 'id');
    }
}
