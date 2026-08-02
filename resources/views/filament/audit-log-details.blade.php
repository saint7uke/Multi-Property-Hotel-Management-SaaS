<div class="grid gap-5 text-sm">
    <dl class="grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Actor</dt>
            <dd class="mt-1 font-medium">{{ $log->user?->name ?? 'Public / system' }}</dd>
            @if ($log->user?->email)
                <dd class="text-gray-500 dark:text-gray-400">{{ $log->user->email }}</dd>
            @endif
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Occurred</dt>
            <dd class="mt-1 font-medium">{{ $log->created_at?->format('M j, Y g:i:s A') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Subject</dt>
            <dd class="mt-1 font-medium">{{ $log->subject_type ? class_basename($log->subject_type) : 'General event' }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Request source</dt>
            <dd class="mt-1 font-medium">{{ $log->ip_address ?? 'Unknown IP address' }}</dd>
        </div>
    </dl>

    <div>
        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Recorded changes</div>
        <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-gray-950 p-4 text-xs leading-6 text-gray-100">{{ json_encode($log->changes ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>

    <div>
        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">User agent</div>
        <p class="mt-1 break-words text-gray-600 dark:text-gray-300">{{ $log->user_agent ?: 'Not recorded' }}</p>
    </div>
</div>
