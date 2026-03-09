<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class InventoryGallery extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.inventory-gallery';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public function getProducts(): Collection
    {
        $warehouseId = $this->pageFilters['warehouse_id'] ?? null;
        $search = $this->pageFilters['search'] ?? null;
        $categoryId = $this->pageFilters['category_id'] ?? null;
        $categoryType = $this->pageFilters['category_type'] ?? null;
        $status = $this->pageFilters['status'] ?? null;
        $sort = $this->pageFilters['sort'] ?? 'name_asc';

        if (! $warehouseId) {
            return collect();
        }

        return Product::query()
            ->with([
                'warehouse.user',
                'categories',
            ])
            ->where('warehouse_id', $warehouseId)
            ->when(
                filled($search),
                fn (Builder $query) => $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('categories', function (Builder $categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                })
            )
            ->when(
                filled($categoryId),
                fn (Builder $query) => $query->whereHas('categories', function (Builder $categoryQuery) use ($categoryId) {
                    $categoryQuery->where('categories.id', $categoryId);
                })
            )
            ->when(
                filled($categoryType),
                fn (Builder $query) => $query->whereHas('categories', function (Builder $categoryQuery) use ($categoryType) {
                    $categoryQuery->where('type', $categoryType);
                })
            )
            ->when(
                filled($status),
                function (Builder $query) use ($status) {
                    match ($status) {
                        'active' => $query->where('is_active', true),
                        'inactive' => $query->where('is_active', false),
                        'featured' => $query->where('is_featured', true),
                        'in_stock' => $query->where('stock', '>', 0),
                        'out_of_stock' => $query->where('stock', '<=', 0),
                        default => null,
                    };
                }
            )
            ->when($sort === 'name_asc', fn (Builder $query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn (Builder $query) => $query->orderByDesc('name'))
            ->when($sort === 'price_low_high', fn (Builder $query) => $query->orderBy('selling_price'))
            ->when($sort === 'price_high_low', fn (Builder $query) => $query->orderByDesc('selling_price'))
            ->when($sort === 'stock_low_high', fn (Builder $query) => $query->orderBy('stock'))
            ->when($sort === 'stock_high_low', fn (Builder $query) => $query->orderByDesc('stock'))
            ->when($sort === 'latest', fn (Builder $query) => $query->latest())
            ->when($sort === 'oldest', fn (Builder $query) => $query->oldest())
            ->get();
    }

    public function getFeaturedImage(Product $product): ?string
    {
        $images = $this->getProductImages($product);

        return $images[0] ?? null;
    }

    public function getCategoriesByType(Product $product, string $type): array
    {
        return $product->categories
            ->where('type', $type)
            ->pluck('name')
            ->values()
            ->toArray();
    }

    protected function getProductImages(Product $product): array
    {
        $images = [];

        if (is_array($product->images)) {
            $images = $product->images;
        } elseif (is_string($product->images)) {
            $decoded = json_decode($product->images, true);
            $images = is_array($decoded) ? $decoded : [];
        }

        return collect($images)
            ->map(function ($image) {
                if (blank($image)) {
                    return null;
                }

                if (is_array($image)) {
                    $image = $image['url'] ?? $image['path'] ?? $image['image'] ?? null;
                }

                if (! $image) {
                    return null;
                }

                if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                    return $image;
                }

                return Storage::disk('public')->url($image);
            })
            ->filter()
            ->values()
            ->all();
    }
}