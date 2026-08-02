<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Models\AuditLog;
use App\Services\PropertyLifecycleWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    /** @var array<string, mixed> */
    private array $originalState = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPublicPage')
                ->label('View public page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => route('hotels.show', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->getRecord()->status === 'active'),
            Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') && $this->getRecord()->status === 'active')
                ->requiresConfirmation()
                ->modalDescription('The property will be hidden from public pages while all operational history remains intact.')
                ->action(function (Action $action): void {
                    app(PropertyLifecycleWorkflow::class)->setStatus($this->getRecord(), 'inactive', auth()->user());
                    $this->refreshFormData(['status']);
                    $action->success();
                })
                ->successNotificationTitle('Property deactivated'),
            Action::make('activate')
                ->label('Reactivate')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->hasRole('admin') && $this->getRecord()->status === 'inactive')
                ->requiresConfirmation()
                ->action(function (Action $action): void {
                    app(PropertyLifecycleWorkflow::class)->setStatus($this->getRecord(), 'active', auth()->user());
                    $this->refreshFormData(['status']);
                    $action->success();
                })
                ->successNotificationTitle('Property reactivated'),
            DeleteAction::make()
                ->label('Delete permanently')
                ->visible(fn (): bool => PropertyResource::canDelete($this->getRecord()))
                ->modalDescription('This permanently removes the property and uploaded images. It is available only when the inactive property has no operational dependencies.')
                ->using(fn ($record): bool => app(PropertyLifecycleWorkflow::class)->delete($record, auth()->user())),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalState = $this->getRecord()->getOriginal();
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $changed = collect($record->getChanges())->except('updated_at');

        if ($changed->isEmpty()) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'properties.cms_updated',
            'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'changes' => [
                'before' => $changed->keys()->mapWithKeys(fn (string $key): array => [$key => $this->originalState[$key] ?? null])->all(),
                'after' => $changed->all(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
