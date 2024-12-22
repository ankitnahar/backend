<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'ticket_status';
    protected $hidden = [ ];
    public $timestamps = false;    
}