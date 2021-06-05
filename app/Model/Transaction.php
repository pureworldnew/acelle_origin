<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // wait status
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';
    const STATUS_SUCCESS = 'success';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'message', 'error', 'status'
    ];

    /**
     * Find item by uid.
     *
     * @return object
     */
    public static function findByUid($uid)
    {
        return self::where('uid', '=', $uid)->first();
    }

    /**
     * Bootstrap any application services.
     */
    public static function boot()
    {
        parent::boot();

        // Create uid when creating list.
        static::creating(function ($item) {
            // Create new uid
            $item->uid = uniqid();
        });
    }

    /**
     * Invoice.
     */
    public function invoice()
    {
        return $this->belongsTo('Acelle\Model\Invoice');
    }

    /**
     * Is failed.
     */
    public function isFailed()
    {
        return $this->status == self::STATUS_FAILED;
    }

    /**
     * Set failed.
     */
    public function setFailed($error=null)
    {
        $this->status = self::STATUS_FAILED;
        $this->error = $error;
        $this->save();
    }

    /**
     * Set as success.
     */
    public function setSuccess()
    {
        $this->status = self::STATUS_SUCCESS;
        $this->save();
    }
}
