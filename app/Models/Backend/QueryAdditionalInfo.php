<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QueryAdditionalInfo extends Model {

    protected $guarded = [ ];

    protected $table = 'query_additional_info';
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
        return $this->hasMany(\App\Models\Backend\QueryAdditionalDocument::class, 'query_add_id', 'id');
    }
}
