<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentFile extends Model
{
    use HasFactory, SoftDeletes;

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function studentPayment() {
        return $this->belongsTo(StudentPayment::class);
    }

    public function studentRegistrarFile() {
        return $this->belongsTo(StudentRegistrarFile::class);
    }
}
