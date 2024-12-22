<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    protected $guarded = [];

    protected $table = 'ticket_type';
    protected $hidden = [];
    public $timestamps = false;    
}