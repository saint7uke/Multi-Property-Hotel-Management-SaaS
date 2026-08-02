<?php

namespace App\Filament\Resources\HousekeepingTasks\Pages;

use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Services\HousekeepingWorkflow;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHousekeepingTask extends CreateRecord
{
    protected static string $resource = HousekeepingTaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(HousekeepingWorkflow::class)->save($data, auth()->user());
    }
}
