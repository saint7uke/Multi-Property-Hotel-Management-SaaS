<?php

namespace App\Filament\Resources\HousekeepingTasks\Pages;

use App\Filament\Resources\HousekeepingTasks\HousekeepingTaskResource;
use App\Services\HousekeepingWorkflow;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditHousekeepingTask extends EditRecord
{
    protected static string $resource = HousekeepingTaskResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(HousekeepingWorkflow::class)->save($data, auth()->user(), $record);
    }
}
