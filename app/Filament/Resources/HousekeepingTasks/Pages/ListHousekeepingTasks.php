<?php

namespace App\Filament\Resources\HousekeepingTasks\Pages;

use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHousekeepingTasks extends ListRecords
{
    protected static string $resource = HousekeepingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
