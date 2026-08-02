<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'properties.created',
            'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'changes' => ['after' => $record->toArray()],
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
