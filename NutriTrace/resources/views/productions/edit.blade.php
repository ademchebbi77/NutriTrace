@extends('layouts.layout')

@section('title', __('productions.edit_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">
        {{ __('productions.edit_title') }}
        <small class="text-gray-600">{{ $production->lot->lot_number }}</small>
    </h1>

    <form method="POST" action="{{ area_route('productions.update', $production) }}" novalidate>
        @csrf
        @method('PUT')
        @include('productions._form')
    </form>

@endsection
