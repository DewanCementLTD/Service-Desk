{{-- The application logo — the Yousuf Dewan Companies IT Service Desk emblem.
     This is the site's own mark (not a customer/company logo) and is reused
     as-is in outbound ticket emails; see resources/views/emails/ticket.blade.php
     and public/images/logo-email.png for the full lockup used there. --}}
@props(['class' => 'h-9 w-9'])

<img src="{{ asset('images/logo-icon.png') }}"
     alt="{{ config('app.name') }}"
     {{ $attributes->merge(['class' => $class . ' shrink-0 object-contain']) }}>
