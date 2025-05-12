<?php

namespace App\Providers;

use App\Models\PlatformAccount;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\ServiceProvider;

class FilamentNavigationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        \Filament\Panel::configureUsing(function ($panel) {
            $fanpages = PlatformAccount::select('id', 'name', 'page_id')->get();

            \Illuminate\Support\Facades\Log::info("Number of Fanpages found for navigation: " . $fanpages->count());
            \Illuminate\Support\Facades\Log::info("Fanpage data: " . json_encode($fanpages));

            $navigationItems = [];
            foreach ($fanpages as $fanpage) {
                $navItem = NavigationItem::make($fanpage->name)
                    ->url("/admin/fanpage-messages/{$fanpage->page_id}")
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->group('Quản Lý Tin Nhắn')
                    ->sort(0);

                \Illuminate\Support\Facades\Log::info("Created navigation item: " . json_encode([
                    'label' => $fanpage->name,
                    'url' => "/admin/fanpage-messages/{$fanpage->page_id}"
                ]));

                $navigationItems[] = $navItem;
            }

            // Đăng ký navigation mà không cần getItems()
            $panel->navigation(function (NavigationBuilder $builder) use ($navigationItems) {
                $builder->items([
                    NavigationGroup::make('Quản Lý Tin Nhắn')
                        ->items($navigationItems),
                ]);
                return $builder;
            });

            return $panel;
        });
    }
}