<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorProfile extends Model
{
   protected $fillable = [
        'name',
        'user_id',
        'bio',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function donations()
    {
        return $this->hasMany(Donation::class,'donor_profile_id');
    }
}
