@props(['name', 'class' => 'h-5 w-5'])
{{-- Icône Lucide en SVG inline : <x-svg-icon name="receipt" class="h-5 w-5" /> --}}
{!! svg('lucide-'.$name, $class, ['aria-hidden' => 'true', 'stroke-width' => '1.9'])->toHtml() !!}
