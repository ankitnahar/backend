<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserhierarchyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_hierarchy', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('user_id', 5)->unsigned();
            $table->smallInteger('service_id', 5)->unsigned();
            $table->smallInteger('team_id', 5)->unsigned();
            $table->smallInteger('designation_id', 5)->unsigned();
            $table->smallInteger('DH', 5)->unsigned();
            $table->smallInteger('BUH', 5)->unsigned();
            $table->smallInteger('TAM', 5)->unsigned();
            $table->smallInteger('ATAM', 5)->unsigned();
            $table->smallInteger('TL', 5)->unsigned();
            $table->smallInteger('ATL', 5)->unsigned();
            $table->smallInteger('RM', 5)->unsigned();
            $table->smallInteger('ARM', 5)->unsigned();
            $table->smallInteger('RL', 5)->unsigned();
            $table->smallInteger('ARL', 5)->unsigned();
            $table->smallInteger('QM', 5)->unsigned();
            $table->smallInteger('AQM', 5)->unsigned();
            $table->smallInteger('QL', 5)->unsigned();
            $table->smallInteger('AQL', 5)->unsigned();
            $table->smallInteger('STH', 5)->unsigned();
            $table->smallInteger('TH', 5)->unsigned();
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
        Schema::dropIfExists('user_hierarchy');
    }
}
