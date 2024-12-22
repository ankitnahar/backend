<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetMasterActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [ ];

    protected $table = 'worksheet_master_checklist';
    public $timestamps = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ ];


    /**
     * Get the user related to the client
     *
     * @return mixed
     */
    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
}
