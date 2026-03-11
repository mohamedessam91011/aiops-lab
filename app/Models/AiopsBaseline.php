<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiopsBaseline extends Model
{
    protected $fillable = ['metric_name', 'value', 'window_start', 'window_end'];
}