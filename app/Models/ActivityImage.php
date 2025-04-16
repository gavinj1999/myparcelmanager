<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityImage extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityImageFactory> */
    use HasFactory;

    protected $fillable = ['activity_date', 'image_path'];

}
