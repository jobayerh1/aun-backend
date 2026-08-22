<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaintenanceSmsSchedulesTable extends Migration
{
    public function up()
    {
        Schema::create('maintenance_sms_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('transaction_id')->index();
            $table->unsignedInteger('contact_id')->index();
            $table->string('mobile', 30);
            $table->unsignedSmallInteger('day_offset'); // 60/90/120
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('scheduled'); // scheduled|failed|cancelled
            $table->text('api_response')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'day_offset'], 'txn_day_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_sms_schedules');
    }
}
