<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserauditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('useraudit', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('user_id', 5)->unsigned();
            $table->string('changes', 255);
            $table->text('type');
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
        Schema::dropIfExists('useraudit');
    }
}
