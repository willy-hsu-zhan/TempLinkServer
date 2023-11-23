<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model;

class Abnormaltemp extends Model
{
    protected $collection = 'abnormaltemp';
    protected $fillable = ['user_id', 'machine_id', 'temp', 'time','lineID'];
}
