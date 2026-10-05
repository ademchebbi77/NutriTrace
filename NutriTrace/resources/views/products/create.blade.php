@extends('layouts.layout')

@section('title', __('products.create_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('products.create_title') }}</h1>

    <form method="POST" action="{{ area_route('products.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('products._form')
    </form>

@endsection
