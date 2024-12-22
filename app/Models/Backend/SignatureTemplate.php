<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class SignatureTemplate extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'signature_template';
    protected $hidden = [ ];
    public $timestamps = false;

}
