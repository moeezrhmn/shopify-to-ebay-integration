@extends('layouts.app')


@section('content')
    <div class=" d-flex align-items-start pt-5" style="min-height: 90vh;">
        <div class="container pb-5 mb-5 ">
            <div class="row mb-3">
                <div class="col">
                    <h3> Item Specifics </h3>
                </div>
                <div class="col">
                    <div class="buttons d-flex justify-content-end ">
                        <button data-bs-toggle="modal" data-bs-target="#add_aspect_modal" class="btn btn-dark"> Add New
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="p-3 border rounded">
                        <table class="table">
                            <thead>
                                <th> Name </th>
                                <th> Keywords </th>
                                <th> Action </th>
                            </thead>
                            <tbody>
                                @foreach ($item_specifics as $aspect)
                                    <tr>
                                        <td> {{ $aspect->aspect_name }} </td>
                                        @php
                                            $aspect_values = json_decode($aspect->aspect_values);
                                        @endphp
                                        <td>
                                            @if (!empty($aspect_values))
                                                <table class="table border">
                                                    <thead>
                                                        <tr>
                                                            <th>keys</th>
                                                            <th>Values</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($aspect_values as $aspect_value)
                                                            <tr>
                                                                <td> {{ implode(', ', $aspect_value->keys) }} </td>
                                                                <td> {{ ($aspect_value->value) }} </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('item_specifics.add_keywords', ['aspect_id' => $aspect->id]) }}"
                                                class="btn btn-sm btn-dark"> Add Keywords </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="add_aspect_modal" tabindex="-1" aria-labelledby="add_aspect_modalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('item_specifics.add_aspect') }}" class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="add_aspect_modalLabel">Add New Aspect</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        @csrf
                        <div class="col">
                            <label for="aspect_name">Aspect Name:</label>
                            <input id="aspect_name" class="form-control" name="aspect_name" type="text">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection
