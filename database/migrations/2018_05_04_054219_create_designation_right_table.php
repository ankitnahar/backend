<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDesignationRightTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('designation_tab_right', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('tab_id', 5)->unsigned();
            $table->smallInteger('designation_id', 5)->unsigned();
            $table->tinyInteger('view');
            $table->tinyInteger('add_edit');
            $table->tinyInteger('delete');
            $table->text('other_right');
            $table->dateTime('created_on');
            $table->smallInteger('created_by', 5)->unsigned();
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
        Schema::dropIfExists('designation_tab_right');
    }
}
