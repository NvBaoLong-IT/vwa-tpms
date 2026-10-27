@props(['route'])
<svg class="office-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if(str_starts_with($route, 'dashboard'))
        <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
    @elseif(str_contains($route, 'notification'))
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
    @elseif(str_contains($route, 'students') || str_starts_with($route, 'users'))
        <circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 5v2"/>
    @elseif(str_starts_with($route, 'programs') || str_starts_with($route, 'courses'))
        <path d="M12 5v15M3 5c3-1 6-1 9 1 3-2 6-2 9-1v14c-3-1-6-1-9 1-3-2-6-2-9-1Z"/>
    @elseif(str_starts_with($route, 'diplomas'))
        <circle cx="12" cy="9" r="6"/><path d="m8 14-2 7 6-3 6 3-2-7m-7-5 2 2 3-3"/>
    @elseif(str_starts_with($route, 'reports'))
        <path d="M4 3v18h17M8 16v-4m5 4V8m5 8V5"/>
    @else
        <rect x="4" y="4" width="16" height="17" rx="3"/><path d="M9 3h6v3H9zm-1 9 2 2 5-5m-7 9h8"/>
    @endif
</svg>
