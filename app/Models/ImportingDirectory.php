<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportingDirectory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ImportingDirectory';

    protected $fillable = [
        'FIO',
        'DepartmentMOName',
        'Division',
        'Post',
        'ExternalPhone',
        'InternalPhone',
        'Address',
        'Room'
    ];
}
