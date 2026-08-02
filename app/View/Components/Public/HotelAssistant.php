<?php

namespace App\View\Components\Public;

use App\Services\HotelAssistantContent;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class HotelAssistant extends Component
{
    public function __construct(public HotelAssistantContent $content) {}

    public function render(): View
    {
        return view('components.public.hotel-assistant', [
            'assistant' => $this->content->payload(),
        ]);
    }
}
