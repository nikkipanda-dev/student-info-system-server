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
        Schema::create('student_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->boolean('is_full');
            $table->boolean('is_installment');
            $table->string('mode_of_payment');
            $table->date('date_paid');
            $table->decimal('amount_paid', 19, 2, true);
            $table->decimal('balance', 19, 2, true)->nullable();
            $table->string('course');
            $table->string('year');
            $table->string('term');
            $table->string('slug')->unique();
            $table->string('status');
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
        Schema::dropIfExists('student_payments');
    }
};
