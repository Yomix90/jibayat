<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-full bg-navy px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#2B3A52] focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 active:scale-[.98] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
