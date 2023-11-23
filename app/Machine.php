<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model;
use App\Temperature as TemperEloquent;



class Machine extends Model
{
    
    
    protected $collection = 'machine';
    protected $fillable = ['_id', 'name', 'location', 'coordinate'];

    public function temper()
    {
        return $this->hasMany(TemperEloquent::class);
    }
}