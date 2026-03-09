<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'warehouse_id',
        'name',
        'brand',
        'slug',
        'sku',
        'barcode',
        'description',
        'details',
        'images',
        'cost_price',
        'selling_price',
        'compare_at_price',
        'stock',
        'reserved_stock',
        'damaged_stock',
        'low_stock_threshold',
        'weight',
        'length',
        'width',
        'height',
        'dimension_unit',
        'weight_unit',
        'is_active',
        'is_featured',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }
}
