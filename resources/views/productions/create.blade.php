@extends('layouts.layout')

@section('title', __('productions.create_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('productions.create_title') }}</h1>

    <form method="POST" action="{{ area_route('productions.store') }}" novalidate>
        @csrf
        @include('productions._form')
    </form>

@endsection
