<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QuoteTerm extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];

    protected $table = 'quote_term';
    protected $hidden = [ ];
    public $timestamps = false;

 
}
