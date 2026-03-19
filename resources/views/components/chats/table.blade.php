@props([
    'class' => 'mt-4',
])

<flux:table {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</flux:table>
