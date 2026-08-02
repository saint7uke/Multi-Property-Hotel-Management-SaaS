<?php

namespace App\Services;

use Illuminate\Support\Arr;

class HotelAssistantContent
{
    public function payload(): array
    {
        $content = config('hotel_assistant');

        return [
            'welcome' => $content['welcome'],
            'fallback' => $content['fallback'],
            'faqs' => collect($content['faqs'])
                ->map(fn (array $faq): array => [
                    ...Arr::except($faq, ['actions']),
                    'actions' => $this->resolveActions($faq['actions'] ?? []),
                ])
                ->values()
                ->all(),
            'quickActions' => $this->resolveActions($content['quick_actions']),
            'fallbackActions' => $this->resolveActions($content['fallback_actions']),
            'staffAction' => $this->resolveAction($content['staff_action']),
        ];
    }

    /** @param array<int, array<string, string>> $actions */
    private function resolveActions(array $actions): array
    {
        return collect($actions)->map($this->resolveAction(...))->values()->all();
    }

    /** @param array<string, string> $action */
    private function resolveAction(array $action): array
    {
        if (isset($action['route'])) {
            $action['url'] = route($action['route']).(isset($action['fragment']) ? '#'.$action['fragment'] : '');
            unset($action['route'], $action['fragment']);
        }

        return $action;
    }
}
