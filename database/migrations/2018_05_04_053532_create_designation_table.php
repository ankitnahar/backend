<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDesignationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('designation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('designation_name', 255);
            $table->string('designation_identifier', 255);
            $table->tinyInteger('is_active');
            $table->dateTime('created_on');
            $table->smallInteger('created_by', 5)->unsigned();
            $table->dateTime('modified_on');
            $table->smallInteger('modified_by', 5)->unsigned();
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
        Schema::dropIfExists('designation');
    }
}
