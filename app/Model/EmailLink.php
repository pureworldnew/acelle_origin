<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;

class EmailLink extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'link',
    ];

    /**
     * Association with mailList through mail_list_id column.
     */
    public function email()
    {
        return $this->belongsTo('Acelle\Model\Email');
    }

    /**
     * Bootstrap any application services.
     */
    public static function boot()
    {
        parent::boot();

        // Create uid when creating automation.
        static::creating(function ($item) {
            // Create new uid
            $item->uid = uniqid();
        });
    }

    /**
     * Find item by uid.
     *
     * @return object
     */
    public static function findByUid($uid)
    {
        return self::where('uid', '=', $uid)->first();
    }
}
