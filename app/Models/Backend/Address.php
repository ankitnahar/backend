<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Address extends Model {

    protected $table = 'entity_address';
    protected $fillable = ['id', 'entity_id', 'type', 'street_address', 'suburb', 'state_id', 'postcode', 'created_by', 'created_on', 'modified_by', 'modified_on'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function entityId() {
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id');
    }

}
