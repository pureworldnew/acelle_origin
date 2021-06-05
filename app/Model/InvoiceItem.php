<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'item_id', 'item_type', 'amount', 'title', 'description'
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
     * Invoice.
     */
    public function invoice()
    {
        return $this->belongsTo('Acelle\Model\Invoice');
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
     * Total amount.
     *
     * @return void
     */
    public function total()
    {
        return $this->amount + $this->tax - $this->discount;
    }
}
