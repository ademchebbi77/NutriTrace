@extends('layouts.layout')

@section('title', __('ai.distributor.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.distributor.title') }}</h1>
    <p class="text-gray-600 mb-4">{{ __('ai.distributor.subtitle') }}</p>

    <div class="row">
        <div class="col-xl-9">
            <div class="card shadow mb-4">
                <div class="card-body">
                    @include('ai.partials.assistant', [
                        'url' => route('distributeur.assistant.ask'),
                        'available' => $available,
                        'texts' => 'ai.distributor',
                        'offline' => $training ? __('ai.distributor.training') : __('ai.distributor.offline'),
                    ])
                </div>
            </div>
        </div>
    </div>

@endsection
