<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('user_bio_id', 5)->unsigned();
            $table->string('user_fname', 255);
            $table->string('user_lname', 255);
            $table->string('userfullname', 255);
            $table->string('user_login_name', 100);
            $table->string('zoho_login_name', 100);
            $table->string('email', 100);
            $table->string('password', 100);
            $table->date('user_birthdate');
            $table->dateTime('user_register_date');
            $table->dateTime('user_lastlogin');
            $table->smallInteger('shift_id', 5);
            $table->smallInteger('first_approval_user', 5);
            $table->smallInteger('second_approval_user', 5);
            $table->smallInteger('redmine_user_id', 5);
            $table->smallInteger('user_writeoff', 5);
            $table->tinyInteger('user_timesheet_fillup_flag');
            $table->smallInteger('writeoffstaff', 5);
            $table->tinyInteger('is_active');
            $table->smallInteger('created_by');
            $table->dateTime('created_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('user');
    }

}
