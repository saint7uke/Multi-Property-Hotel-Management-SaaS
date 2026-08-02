<?php

namespace App\Filament\Resources\NewsletterSubscriptions\Pages;

use App\Filament\Resources\NewsletterSubscriptions\NewsletterSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterSubscriptions extends ListRecords
{
    protected static string $resource = NewsletterSubscriptionResource::class;

    public function getTitle(): string
    {
        return 'Newsletter subscribers';
    }
}
