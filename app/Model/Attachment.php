<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'size',    ];

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

    /**
     * Remove attachment.
     *
     * @return object
     */
    public function remove()
    {
        unlink($this->file);
        $this->delete();
    }
}
