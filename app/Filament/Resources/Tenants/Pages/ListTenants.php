<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    public function getTabs(): array
    {
        $allTower = Tenant::query()
            ->distinct('tower')
            ->pluck('tower')
            ->reject(fn ($tower) => empty($tower))
            ->mapWithKeys(function ($tower) {
                return [$tower => Tab::make()
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('tower', $tower))];
            })->toArray();

        return [
            'all' => Tab::make(),
            ...$allTower,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
