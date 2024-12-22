<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntitySoftware extends Model {

    protected $guarded = ['id'];
    protected $fillable = ['entity_id', 'software_id', 'username', 'password', 'link', 'notes', 'is_active', 'created_by', 'created_on'];
    protected $table = 'entity_software';
    protected $hidden = [];
    public $timestamps = false;

    public function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modified_by() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function softwareId() {
        return $this->belongsTo(\App\Models\Backend\Software::class, 'software_id', 'id');
    }

    public function entityId() {
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id');
    }

    static public function decryptPassword($data) {
        $i=0;
        foreach ($data as $key => $value) {
            $password = \Illuminate\Support\Facades\Crypt::decrypt($value->password);
            $data[$i]->password = $password;
            $i++;
        }
        return $data;
    }

}
