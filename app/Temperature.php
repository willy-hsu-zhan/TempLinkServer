<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model;
use App\Machine as MachineEloquent;

class Temperature extends Model
{
    
    protected $collection = 'temperature';
    protected $fillable = ['user_id', 'machine_id', 'temp', 'time'];


    public function machine()
    {
        return $this->belongsTo(MachineEloquent::class);
    }
}