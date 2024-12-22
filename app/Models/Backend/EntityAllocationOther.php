<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityAllocationOther extends Model
{
    //
    protected $table = 'entity_allocation_other';
    protected $guarded = ['id'];
    protected $hidden = [ ];
    public $timestamps = false;
}
