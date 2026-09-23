<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CategoryStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Category Status';

    protected ?string $description = 'Camera availability per category';

    protected int|string|array $columnSpan = 'full';

    public function getStats(): array
    {
        return Category::query()
            ->withCount(['cameras', 'cameras as online_count' => fn ($q) => $q->where('status', 'online')])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => Stat::make($category->name, $category->online_count)
                ->description("{$category->online_count}/{$category->cameras_count} online")
                ->descriptionIcon('heroicon-m-signal')
                ->icon('heroicon-o-video-camera')
                ->color($category->cameras_count > 0 && $category->online_count === $category->cameras_count ? 'success' : ($category->online_count > 0 ? 'warning' : 'danger')))
            ->all();
    }
}