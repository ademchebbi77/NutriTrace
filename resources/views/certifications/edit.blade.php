@extends('layouts.layout')

@section('title', __('certifications.edit_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('certifications.edit_title') }}</h1>

    <form method="POST" action="{{ area_route('certifications.update', $certification) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('certifications._form')
    </form>

@endsection
