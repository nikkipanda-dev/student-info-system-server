<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model
{
    use HasFactory, HasApiTokens, SoftDeletes;

    public function studentPayments() {
        return $this->hasMany(StudentPayment::class);
    }

    public function studentFiles() {
        return $this->hasMany(StudentFile::class);
    }
}
