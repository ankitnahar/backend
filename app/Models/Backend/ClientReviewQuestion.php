<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ClientReviewQuestion extends Model
{
    // Table name which we used from database
    protected $table = 'new_client_review_question_by_client';
    protected $fillable = [];
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
}