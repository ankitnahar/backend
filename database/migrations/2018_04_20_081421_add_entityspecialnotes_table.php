<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEntityspecialnotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entity_specialnotes', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('entity_id', 10);
            $table->string('service_id', 10);
            $table->string('special_note', 500);
            $table->datetime('expiry_on');
            $table->int('created_by');
            $table->datetime('created_on');
            $table->int('modified_by');
            $table->datetime('modified_on');
            $table->int('archived_by');
            $table->datetime('archived_on');
            $table->int('is_archive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('entity_specialnotes');
    }
}
