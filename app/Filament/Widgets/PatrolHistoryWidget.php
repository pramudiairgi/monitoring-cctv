<?php

namespace App\Filament\Widgets;

use App\Models\PatrolLog;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class PatrolHistoryWidget extends Widget
{
    protected string $view = 'filament.widgets.patrol-history-heatmap';

    protected int|string|array $columnSpan = 'full';

    protected int $weeks = 12;

    public function getHeading(): string
    {
        return 'Riwayat Patroli';
    }

    public function getDescription(): string
    {
        return 'Hari-hari dengan patroli aktif (kamera patroli online) dalam '.$this->weeks.' minggu terakhir';
    }

    protected function getViewData(): array
    {
        return [
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'weeks' => $this->getWeeks(),
            'hasData' => PatrolLog::query()
                ->where('patrol_online_count', '>', 0)
                ->where('checked_at', '>=', $this->getStartDate())
                ->exists(),
        ];
    }

    protected function getStartDate(): Carbon
    {
        return now()->startOfWeek()->subWeeks($this->weeks - 1);
    }

    protected function getWeeks(): array
    {
        $start = $this->getStartDate();
        $end = now()->endOfWeek();

        $counts = PatrolLog::query()
            ->where('patrol_online_count', '>', 0)
            ->whereBetween('checked_at', [$start, $end])
            ->get()
            ->groupBy(fn (PatrolLog $log) => $log->checked_at->toDateString())
            ->map->count();

        $maxCount = $counts->max() ?? 0;

        $weeks = [];
        $date = $start->copy();
        $previousMonth = null;

        while ($date->lte($end)) {
            $days = [];

            for ($day = 0; $day < 7; $day++) {
                $key = $date->toDateString();
                $count = $counts->get($key, 0);

                $days[] = [
                    'date' => $date->copy(),
                    'count' => $count,
                    'level' => $this->level($count, $maxCount),
                ];

                $date->addDay();
            }

            $month = $days[0]['date']->month;
            $weeks[] = [
                'monthLabel' => $month !== $previousMonth ? $days[0]['date']->format('M') : null,
                'days' => $days,
            ];
            $previousMonth = $month;
        }

        return $weeks;
    }

    protected function level(int $count, int $maxCount): int
    {
        if ($count <= 0) {
            return 0;
        }

        if ($maxCount <= 0) {
            return 1;
        }

        return (int) ceil(($count / $maxCount) * 4);
    }
}