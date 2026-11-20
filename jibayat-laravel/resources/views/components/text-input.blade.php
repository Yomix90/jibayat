@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-line bg-white shadow-sm focus:border-gold focus:ring-gold']) }}>
