<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    public function beneficiaries()
    {
        return $this->belongsToMany(BeneficiaryProfile::class, 'beneficiary_types');
    }
    public function volunteerTasks()
    {
        return $this->hasMany(VolunteerTask::class);
    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
