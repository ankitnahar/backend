<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Card extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'billing_card';
    protected $hidden = [];
    public $timestamps = false;

    public static function getCard() {
        return Card::get()->pluck('name', 'id')->toArray();
    }

}
