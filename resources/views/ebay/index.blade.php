@extends('layouts.app')


@section('content')
    <div class=" d-flex align-items-start pt-5" style="min-height: 90vh;">
        <div class="container pb-5 mb-5 ">
            <div class="row mb-3">
                <div class="col">
                    <h3> Ebay </h3>
                </div>
                <div class="col">
                    @if (!empty(session('message')))
                    <div class="alert alert-info" role="alert">
                        {{ session('message') }}
                      </div>
                    @endif
                </div>
            </div>
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="p-3 border rounded">
                        <h4>Update item:</h4>
                        <form action="{{route('ebay.complete_item_update')}}" class="row pt-4"  method="post">
                            @csrf
                            <div class="col-6">
                                <input type="text" name="item_id" placeholder="paste ebay item id here" class="form-control">
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-dark" > Update</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <div class="p-3 border rounded">
                        <h4>Update title:</h4>
                        <form  action="{{ route('ebay.title_update') }}" class="row pt-4"  method="post">
                            @csrf
                            <div class="col-6">
                                <input type="text" name="item_id" placeholder="paste ebay item id here" class="form-control">
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-dark" > Update</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <div class="p-3 border rounded">
                        <h4>Update Item specifics:</h4>
                        <form action="{{ route('ebay.item_specifics_update') }}"  class="row pt-4"  method="post">
                            @csrf
                            <div class="col-6">
                                <input type="text"  name="item_id" placeholder="paste ebay item id here" class="form-control">
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-dark" > Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
