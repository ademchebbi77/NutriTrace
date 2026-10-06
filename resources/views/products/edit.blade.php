@extends('layouts.layout')

@section('title', __('products.edit_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('products.edit_title') }}</h1>

    <form method="POST" action="{{ area_route('products.update', $product) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('products._form')
    </form>

@endsection
