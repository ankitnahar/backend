<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class TicketDocument extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'ticket_document';
    protected $hidden = [ ];
    public $timestamps = false;    
}