@extends('layouts.layout')

@section('title', __('certifications.create_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('certifications.create_title') }}</h1>

    <form method="POST" action="{{ area_route('certifications.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('certifications._form')
    </form>

@endsection
