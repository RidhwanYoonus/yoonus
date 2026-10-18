@extends('layouts.app')
@section('content')
<div class="container-x py-12">
    <h1 class="font-display text-4xl">{{ __('shop.nav.wishlist') }}</h1>
    <div class="mt-8 grid-products">
        @forelse ($products as $product)
            <div>@include('partials.product-card')<form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="mt-2 px-1">@csrf<button class="text-xs text-muted underline">{{ __('shop.wishlist.remove') }}</button></form></div>
        @empty
            <p class="col-span-full py-16 text-center text-muted">{{ __('shop.wishlist.empty') }}</p>
        @endforelse
    </div>
</div>
@endsection
