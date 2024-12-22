<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class OtherAccount extends Model {

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
    protected $table = 'other_account';
    public $timestamps = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the user related to the client
     *
     * @return mixed
     */
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

     public static function getAccount() {
        return $otherAccount = OtherAccount::get()->pluck('account_name', 'id')->toArray();
    }
}
