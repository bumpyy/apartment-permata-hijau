<?php

namespace App\Livewire;

use App\Models\Tenant;
use App\Table\Column;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable extends Table
{
    public function query(): Builder
    {
        return Tenant::query();
    }

    public function columns(): array
    {
        return [
            Column::make('name', 'Name'),
            Column::make('email', 'Email'),
        ];
    }

    // public function render()
    // {
    //     return view('livewire.tenants-table');
    // }
}
