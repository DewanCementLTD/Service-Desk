{{-- Sidebar links. Expects $items — each with label, route, active, permission,
     icon. `permission` may be a single code or a list, in which case holding any
     one of them shows the link. --}}
@foreach($items as $item)
    @permission(...(array) $item['permission'])
        @php $isActive = request()->is($item['active']); @endphp
        <a href="{{ route($item['route']) }}"
           @class(['nav-link', 'nav-link-active' => $isActive])
           @if($isActive) aria-current="page" @endif>
            <svg class="h-[18px] w-[18px] shrink-0 {{ $isActive ? 'text-brand-600 dark:text-brand-300' : 'text-slate-400 dark:text-slate-500' }}"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="{{ $item['icon'] }}" />
            </svg>
            <span class="truncate">{{ $item['label'] }}</span>
        </a>
    @endpermission
@endforeach
