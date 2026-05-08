<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Published Posts', Post::where('status', 'published')->count())
                ->description('Total published articles')
                ->color('success')
                ->icon('heroicon-o-document-text'),

            Stat::make('Draft Posts', Post::where('status', 'draft')->count())
                ->description('Posts in draft')
                ->color('warning')
                ->icon('heroicon-o-pencil'),

            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->color('primary')
                ->icon('heroicon-o-users'),

            Stat::make('Newsletter Subscribers', Subscriber::where('status', 'active')->count())
                ->description('Active subscribers')
                ->color('info')
                ->icon('heroicon-o-envelope'),

            Stat::make('Pending Comments', Comment::where('status', 'pending')->count())
                ->description('Awaiting moderation')
                ->color('danger')
                ->icon('heroicon-o-chat-bubble-left-ellipsis'),
        ];
    }
}
