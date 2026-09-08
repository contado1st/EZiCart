<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'category', 
        'price', 'stock', 'image_path', 'is_archived'
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}