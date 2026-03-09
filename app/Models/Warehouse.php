<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Warehouse extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'code',
        'contact_person',
        'email',
        'phone',
        'address',
        'notes',
        'status',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function attributes()
    {
        return $this->hasMany(Attribute::class);
    }
}
