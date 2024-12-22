<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class TicketAssignee extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'ticket_assignee';
    protected $hidden = [ ];
    public $timestamps = false;    
}