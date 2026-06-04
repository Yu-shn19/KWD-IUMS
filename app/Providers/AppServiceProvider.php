<?php

namespace App\Providers;

use App\Models\DisconnectionOrder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

        View::composer('partials.navbar', function ($view) {
            $defaults = [
                'adminDisconnectionAlerts' => collect(),
                'adminDisconnectionAlertCount' => 0,
                'adminLatestDisconnectionForToast' => null,
            ];

            if (! auth()->check() || auth()->user()->role !== 'admin') {
                $view->with($defaults);

                return;
            }

            $alerts = DisconnectionOrder::query()
                ->where('status', 'disconnected')
                ->whereNotNull('disconnected_at')
                ->where('disconnected_at', '>=', now()->subDay())
                ->with(['disconnector:id,name,first_name,last_name'])
                ->orderByDesc('disconnected_at')
                ->limit(15)
                ->get();

            $latest = $alerts->first();
            $toastPayload = null;
            if ($latest) {
                $disconnectorName = 'Disconnector';
                if ($latest->disconnector) {
                    $u = $latest->disconnector;
                    $disconnectorName = $u->name
                        ?: trim(implode(' ', array_filter([$u->first_name ?? '', $u->last_name ?? ''])))
                        ?: $disconnectorName;
                }
                $toastPayload = [
                    'id' => $latest->id,
                    'disconnected_at' => $latest->disconnected_at?->toIso8601String(),
                    'account_no' => $latest->account_no,
                    'account_name' => $latest->account_name,
                    'disconnector_name' => $disconnectorName,
                ];
            }

            $view->with([
                'adminDisconnectionAlerts' => $alerts,
                'adminDisconnectionAlertCount' => $alerts->count(),
                'adminLatestDisconnectionForToast' => $toastPayload,
            ]);
        });
    }
}
