<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\StaffUserWorkflow;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->getRecord()->getRoleNames()->first();

        return $data;
    }

    protected function handleRecordUpdate($record, array $data): User
    {
        return app(StaffUserWorkflow::class)->update($record, $data, auth()->user());
    }
}
