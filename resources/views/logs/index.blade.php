@extends('layouts.app')


@section('content')
<style>
 
    .log-entry {
        word-wrap: break-word;
        word-break: break-all;
        padding: 5px 0;
        border-bottom: 1px solid #ddd;
    }
</style>

<div class="container my-5 ">
    <div class="row ">
        <h2> {{ $log_name }} </h2>
    </div>
    <div class="row  my-3">
        @if(session('message'))
        <div class="alert alert-primary" role="alert">
            {{ session('message') }}
        </div>
        @endif
    </div>
    <div class="row">
        <div class="col">
            <div class="border bg-dark text-white rounded p-3">
                @foreach( $logs as $key => $log )
                <div class="log-entry py-2" style="border-bottom: 1px solid #ddd;">
                    {{ $log }}
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection