<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    protected $fillable = ['review_id', 'seller_id', 'body', 'version'];

    protected $hidden = ['seller_id', 'version'];
}
