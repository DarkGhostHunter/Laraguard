@extends('laraguard::layout')

@section('card-body')
    <p class="text-center">
        {{ __('To proceed, you need to enable Two Factor Authentication.') }}
    </p>
    <div class="col-auto mb-3">
        <a href="{{ $url }}" class="btn btn-primary btn-lg">
            {{ __('Enable') }} &raquo;
        </a>
    </div>
@endsection
