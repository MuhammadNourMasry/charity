<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
     protected $fillable = [
        'title',
        'description',
        'goal_amount',
        'collected_amount',
        'category',
        'status',
        'is_emergency',
        'start_date',
        'end_date',
    ];
    protected $casts = [
        'is_emergency' => 'boolean',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'goal_amount'      => 'decimal:2',
        'collected_amount' => 'decimal:2',
    ];
    protected $appends = ['achieved_amount', 'donors_count', 'progress_percentage'];
    public function donations()
{
    return $this->hasMany(Donation::class, 'campaign_id');
}

public function donorProfiles()
{
    return $this->belongsToMany(DonorProfile::class, 'donations', 'campaign_id', 'donor_id')->distinct();
}
public function getAchievedAmountAttribute()
    {
        return $this->attributes['achieved_amount_sum']
            ?? $this->donations()->where('status', 'completed')->sum('amount');
    }
    public function getDonorsCountAttribute()
    {
        return $this->attributes['donors_count_calc']
            ?? $this->donations()->where('status', 'completed')->distinct('donor_id')->count('donor_id');
    }

    public function getProgressPercentageAttribute()
    {
        if (!$this->goal_amount || $this->goal_amount == 0) {
            return 0;
        }
        return round(($this->achieved_amount / $this->goal_amount) * 100, 2);
    }
}
