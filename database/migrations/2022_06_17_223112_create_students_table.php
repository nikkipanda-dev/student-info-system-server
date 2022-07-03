<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('student_number')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('course');
            $table->string('year');
            $table->string('term');
            $table->boolean('is_enrolled')->default(true);
            $table->boolean('is_dropped')->default(false);
            $table->boolean('is_expelled')->default(false);
            $table->boolean('is_graduate')->default(false);
            $table->string('slug')->unique();
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
        Schema::dropIfExists('students');
    }
};
