<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use App\Models\Category;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class PatrolAlertWidget extends Widget
{
    protected ?string $heading = 'Patrol Alerts';

    protected ?string $description = 'Offline patrol cameras requiring attention';

    protected int|string|array $columnSpan = 'full';

    protected ?int $sorting = 3;

    public function render(): View
    {
        $patrolCategoryId = Category::where('slug', 'patroli')->value('id');
        $offlineCameras = $patrolCategoryId
            ? Camera::where('category_id', $patrolCategoryId)
                ->where('status', 'offline')
                ->where('maintenance', false)
                ->get()
            : collect();

        return view('patrol-alert', [
            'offlineCameras' => $offlineCameras,
        ]);
    }
}
