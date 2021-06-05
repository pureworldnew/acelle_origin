<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAutomationsListsSegmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('automations_lists_segments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('automation_id')->unsigned();
            $table->integer('mail_list_id')->unsigned();
            $table->integer('segment_id')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('automation_id', table('als_automation_id_fk'))->references('id')->on('automations')->onDelete('cascade');
            $table->foreign('mail_list_id', table('als_mail_list_id_fk'))->references('id')->on('mail_lists')->onDelete('cascade');
            $table->foreign('segment_id', table('als_segment_id_fk'))->references('id')->on('segments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('automations_lists_segments');
    }
}
