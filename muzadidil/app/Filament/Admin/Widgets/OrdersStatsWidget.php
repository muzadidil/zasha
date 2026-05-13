<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\PartnerLocation;
use App\Models\TopupRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OrdersStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Order::whereDate('created_at', today())->count();
        $online = PartnerLocation::where('is_online', true)
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->count();
        $pendingTopup = TopupRequest::where('status', TopupRequest::STATUS_PENDING)->count();
        $revenueToday = (int) DB::table('order_claims')
            ->whereDate('claimed_at', today())
            ->where('status', OrderClaim::STATUS_SUCCESS)
            ->sum('fee_amount');

        $avgSearchSeconds = (int) DB::table('orders')
            ->whereNotNull('claimed_at')
            ->whereDate('claimed_at', today())
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, claimed_at)) as avg_sec')
            ->value('avg_sec') ?? 0;

        return [
            Stat::make('Order hari ini', $today)
                ->description('Sejak 00:00')
                ->color('primary'),
            Stat::make('Mitra online', $online)
                ->description('5 menit terakhir')
                ->color('success'),
            Stat::make('Pending top-up', $pendingTopup)
                ->description($pendingTopup > 0 ? 'Perlu approve' : 'Semua bersih')
                ->color($pendingTopup > 0 ? 'warning' : 'gray'),
            Stat::make('Revenue hari ini', 'Rp ' . number_format($revenueToday, 0, ',', '.'))
                ->description('Komisi 5%')
                ->color('success'),
            Stat::make('Rata-rata search', $avgSearchSeconds . ' detik')
                ->description('Order claimed hari ini')
                ->color('info'),
        ];
    }
}
