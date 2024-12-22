<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Documenttype extends Model {

    protected $guarded = ['id'];
    protected $table = 'document_type';

    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'modified_on';

}
