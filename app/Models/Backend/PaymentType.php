<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model {

    protected $guarded = [ ];
    protected $fillable = [ ];
    protected $table = 'payment_type';
    protected $hidden = [ ];
    public $timestamps = false;

}
