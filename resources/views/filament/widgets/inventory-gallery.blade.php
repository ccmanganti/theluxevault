<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $products = $this->getProducts();
        @endphp

        <div
            x-data="{
                lightboxOpen: false,
                lightboxImages: [],
                lightboxIndex: 0,
                lightboxTitle: '',

                openGlobalLightbox(images, index = 0, title = '') {
                    this.lightboxImages = images
                    this.lightboxIndex = index
                    this.lightboxTitle = title
                    this.lightboxOpen = true
                    document.body.classList.add('overflow-hidden')
                },

                closeGlobalLightbox() {
                    this.lightboxOpen = false
                    document.body.classList.remove('overflow-hidden')
                },

                nextGlobalImage() {
                    if (this.lightboxImages.length > 1) {
                        this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxImages.length
                    }
                },

                prevGlobalImage() {
                    if (this.lightboxImages.length > 1) {
                        this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxImages.length) % this.lightboxImages.length
                    }
                },

                goToGlobalImage(index) {
                    this.lightboxIndex = index
                }
            }"
            @open-global-lightbox.window="openGlobalLightbox($event.detail.images, $event.detail.index, $event.detail.title)"
            @keydown.escape.window="if (lightboxOpen) closeGlobalLightbox()"
            @keydown.right.window="if (lightboxOpen) nextGlobalImage()"
            @keydown.left.window="if (lightboxOpen) prevGlobalImage()"
            class="space-y-3"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-500 sm:text-base">
                        Inventory Gallery
                    </h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-500 sm:text-xs">
                        Browse your inventory.
                    </p>
                </div>

                <div class="shrink-0 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">
                    {{ $products->count() }} item(s)
                </div>
            </div>

            @if ($products->count())
                <div class="grid grid-cols-2 gap-2.5 min-[520px]:grid-cols-3 sm:gap-3 xl:grid-cols-4 2xl:grid-cols-5">
                    @foreach ($products as $product)
                        @php
                            $images = array_values(array_filter($this->getProductImages($product)));

                            $roomCategories = $this->getCategoriesByType($product, 'room');
                            $styleCategories = $this->getCategoriesByType($product, 'style');
                            $productTypeCategories = $this->getCategoriesByType($product, 'product_type');
                            $extraCategories = $this->getCategoriesByType($product, 'extra');

                            $allCategoryCount = count($roomCategories) + count($styleCategories) + count($productTypeCategories) + count($extraCategories);
                        @endphp

                        <div
                            x-data="{
                                images: @js($images),
                                activeIndex: 0,
                                next() {
                                    if (this.images.length > 1) {
                                        this.activeIndex = (this.activeIndex + 1) % this.images.length
                                    }
                                },
                                prev() {
                                    if (this.images.length > 1) {
                                        this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length
                                    }
                                },
                                goTo(index) {
                                    this.activeIndex = index
                                }
                            }"
                            class="overflow-hidden rounded-xl border border-gray-200/70 bg-white/90 shadow-sm ring-1 ring-black/5 backdrop-blur-sm transition hover:shadow-md dark:border-white/10 dark:bg-gray-900/80 dark:ring-white/10"
                        >
                            <div class="relative aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800">
                                @if (count($images))
                                    <div class="relative h-full w-full">
                                        <div
                                            class="flex h-full transition-transform duration-300 ease-out"
                                            :style="'transform: translateX(-' + (activeIndex * 100) + '%)'"
                                        >
                                            @foreach ($images as $index => $image)
                                                <div class="h-full w-full shrink-0">
                                                    <button
                                                        type="button"
                                                        class="block h-full w-full"
                                                        @click="$dispatch('open-global-lightbox', {
                                                            images: images,
                                                            index: {{ $index }},
                                                            title: @js($product->name)
                                                        })"
                                                    >
                                                        <img
                                                            src="{{ $image }}"
                                                            alt="{{ $product->name }} image {{ $index + 1 }}"
                                                            class="h-full w-full object-cover"
                                                        >
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if (count($images) > 1)
                                            <button
                                                type="button"
                                                @click.stop="prev()"
                                                class="absolute left-1.5 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-white/75 text-[10px] text-gray-700 shadow-sm backdrop-blur hover:bg-white/95 dark:border-white/10 dark:bg-gray-900/75 dark:text-gray-200 dark:hover:bg-gray-900/95 sm:h-6 sm:w-6 sm:text-xs"
                                            >
                                                ‹
                                            </button>

                                            <button
                                                type="button"
                                                @click.stop="next()"
                                                class="absolute right-1.5 top-1/2 z-10 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full border border-white/40 bg-white/75 text-[10px] text-gray-700 shadow-sm backdrop-blur hover:bg-white/95 dark:border-white/10 dark:bg-gray-900/75 dark:text-gray-200 dark:hover:bg-gray-900/95 sm:h-6 sm:w-6 sm:text-xs"
                                            >
                                                ›
                                            </button>

                                            <div class="absolute bottom-1.5 left-1/2 z-10 flex -translate-x-1/2 gap-1 rounded-full border border-white/30 bg-white/60 px-1.5 py-0.5 backdrop-blur dark:border-white/10 dark:bg-gray-900/60 sm:bottom-2 sm:px-2 sm:py-1">
                                                @foreach ($images as $index => $image)
                                                    <button
                                                        type="button"
                                                        @click.stop="goTo({{ $index }})"
                                                        class="h-1 w-1 rounded-full transition sm:h-1.5 sm:w-1.5"
                                                        :class="activeIndex === {{ $index }}
                                                            ? 'bg-gray-900 dark:bg-white'
                                                            : 'bg-gray-400/70 dark:bg-white/40'"
                                                    ></button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-[10px] text-gray-400 dark:text-gray-500 sm:text-xs">
                                        No image
                                    </div>
                                @endif
                            </div>

                            {{-- Mobile simplified card --}}
                            <div class="space-y-1.5 p-2 sm:hidden">
                                <div class="flex items-start justify-between gap-1.5">
                                    <h3 class="line-clamp-2 min-w-0 text-[11px] font-semibold leading-tight text-gray-900 dark:text-gray-100">
                                        {{ $product->name }}
                                    </h3>

                                    @if ($product->is_active)
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-green-100/80 px-1.5 py-0.5 text-[9px] font-medium text-green-700 dark:bg-green-500/10 dark:text-green-300">
                                            On
                                        </span>
                                    @else
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100/80 px-1.5 py-0.5 text-[9px] font-medium text-gray-700 dark:bg-gray-500/10 dark:text-gray-300">
                                            Off
                                        </span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-1 text-[10px]">
                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Price</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ filled($product->selling_price) ? '$' . number_format($product->selling_price, 2) : '—' }}
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Stock</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ number_format($product->stock ?? 0) }}
                                        </div>
                                    </div>
                                </div>

                                <a
                                    href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('view', ['record' => $product]) }}"
                                    class="inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-2 py-1.5 text-[10px] font-medium text-white transition hover:bg-black dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                >
                                    View
                                </a>
                            </div>

                            {{-- Tablet and desktop full card --}}
                            <div class="hidden space-y-2 sm:block sm:p-2.5">
                                <div class="flex items-start justify-between gap-1.5">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-xs font-semibold leading-tight text-gray-900 dark:text-gray-100">
                                            {{ $product->name }}
                                        </h3>

                                        @if ($product->brand)
                                            <p class="mt-0.5 truncate text-[10px] text-gray-500 dark:text-gray-400">
                                                {{ $product->brand }}
                                            </p>
                                        @endif
                                    </div>

                                    @if ($product->is_active)
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-green-100/80 px-1.5 py-0.5 text-[9px] font-medium text-green-700 dark:bg-green-500/10 dark:text-green-300">
                                            On
                                        </span>
                                    @else
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100/80 px-1.5 py-0.5 text-[9px] font-medium text-gray-700 dark:bg-gray-500/10 dark:text-gray-300">
                                            Off
                                        </span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 gap-1.5 text-[10px]">
                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Price</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ filled($product->selling_price) ? '$' . number_format($product->selling_price, 2) : '—' }}
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Stock</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ number_format($product->stock ?? 0) }}
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">SKU</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $product->sku ?: '—' }}
                                        </div>
                                    </div>

                                    <div class="rounded-md border border-gray-200/70 bg-gray-50/80 p-1.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="text-[9px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Res.</div>
                                        <div class="mt-0.5 truncate font-semibold text-gray-900 dark:text-gray-100">
                                            {{ number_format($product->reserved_stock ?? 0) }}
                                        </div>
                                    </div>
                                </div>

                                @if ($allCategoryCount)
                                    <div class="space-y-1">
                                        @foreach ([
                                            'Room' => [$roomCategories, 'bg-blue-100/80 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'],
                                            'Style' => [$styleCategories, 'bg-purple-100/80 text-purple-700 dark:bg-purple-500/10 dark:text-purple-300'],
                                            'Product Type' => [$productTypeCategories, 'bg-amber-100/80 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
                                            'Extra' => [$extraCategories, 'bg-emerald-100/80 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                                        ] as $label => [$items, $classes])
                                            @if (count($items))
                                                <div>
                                                    <div class="mb-0.5 text-[9px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                                        {{ $label }}
                                                    </div>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($items as $item)
                                                            <span class="rounded-full px-1.5 py-0.5 text-[9px] font-medium {{ $classes }}">
                                                                {{ $item }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-1 pt-0.5">
                                    <a
                                        href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('view', ['record' => $product]) }}"
                                        class="inline-flex min-w-0 items-center justify-center rounded-md bg-gray-900 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-black dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                    >
                                        View
                                    </a>

                                    @if (\App\Filament\Resources\Products\ProductResource::canEdit($product))
                                        <a
                                            href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $product]) }}"
                                            class="inline-flex min-w-0 items-center justify-center rounded-md border border-gray-200/80 bg-white/80 px-2.5 py-1 text-[11px] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-200 dark:hover:bg-white/[0.08]"
                                        >
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-300/80 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    No products found for this warehouse.
                </div>
            @endif

            {{-- Global Lightbox --}}
            <div
                x-cloak
                x-show="lightboxOpen"
                x-transition.opacity
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4"
                @click.self="closeGlobalLightbox()"
            >
                <div class="relative flex h-full w-full max-w-7xl items-center justify-center">
                    <button
                        type="button"
                        @click="closeGlobalLightbox()"
                        class="absolute right-3 top-3 z-30 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-gray-900 shadow-md backdrop-blur hover:bg-white dark:bg-gray-900/90 dark:text-white dark:hover:bg-gray-800"
                    >
                        ✕
                    </button>

                    <template x-if="lightboxImages.length">
                        <div class="relative flex h-full w-full items-center justify-center">
                            <div class="absolute left-3 top-3 z-20 rounded-full bg-black/45 px-3 py-1 text-xs font-medium text-white backdrop-blur">
                                <span x-text="lightboxTitle"></span>
                                <span class="mx-1">•</span>
                                <span x-text="(lightboxIndex + 1) + ' / ' + lightboxImages.length"></span>
                            </div>

                            <img
                                :src="lightboxImages[lightboxIndex]"
                                :alt="lightboxTitle"
                                class="max-h-[90vh] max-w-[95vw] rounded-xl object-contain shadow-2xl"
                            >

                            <template x-if="lightboxImages.length > 1">
                                <div>
                                    <button
                                        type="button"
                                        @click="prevGlobalImage()"
                                        class="absolute left-3 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-lg text-gray-900 shadow-md backdrop-blur hover:bg-white dark:bg-gray-900/90 dark:text-white dark:hover:bg-gray-800"
                                    >
                                        ‹
                                    </button>

                                    <button
                                        type="button"
                                        @click="nextGlobalImage()"
                                        class="absolute right-3 top-1/2 z-20 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-lg text-gray-900 shadow-md backdrop-blur hover:bg-white dark:bg-gray-900/90 dark:text-white dark:hover:bg-gray-800"
                                    >
                                        ›
                                    </button>

                                    <div class="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 gap-2 rounded-full bg-black/45 px-3 py-2 backdrop-blur">
                                        <template x-for="(image, index) in lightboxImages" :key="index">
                                            <button
                                                type="button"
                                                @click="goToGlobalImage(index)"
                                                class="h-2.5 w-2.5 rounded-full transition"
                                                :class="lightboxIndex === index ? 'bg-white' : 'bg-white/40'"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>