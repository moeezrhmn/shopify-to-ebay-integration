@extends('layouts.app')


@section('content')
    <div class=" d-flex align-items-center" style="min-height: 90vh;">
        <div class="container">
            <div class="row text-center mb-4">
                <div class="col">
                    <h3> Add Keywords for {{ $aspect->aspect_name }} </h3>
                </div>
                @if (!empty(session('message')))
                <div class="alert alert-info" role="alert">
                    {{  session('message') }}
                  </div>
                @endif
            </div>
            <div class="row justify-content-center  ">
                <div class="col-md-10">
                    <div class="form-box p-3 border rounded">
                        <form method="POST"
                            action="{{ route('item_specifics.store_keywords', ['aspect_id' => $aspect->id]) }}"
                            class="row">
                            <div class="col-12 mb-3 " id="inputs_rows">
                                @csrf
                                @if (!empty($aspect->aspect_values))
                                    @foreach ($aspect->aspect_values as $index => $aspect_value)
                                        <div class="row mb-3">
                                            <div class=" col-6 col-md-8 ">
                                                <label for="aspect_name"> Keys </label>
                                                <input id="aspect_name" type="text"
                                                    placeholder="e.g. button, button-up, button up"
                                                    value="{{ implode(', ', $aspect_value->keys) }}"
                                                    name="aspects[{{ $index }}][keys]" class="form-control">
                                            </div>
                                            <div class="col ">
                                                <label for="aspect_name"
                                                    title="If you leave its value empty then app will ensure that not to add current Item Specific when given keys match.">
                                                    Value </label>
                                                <input id="aspect_name" type="text" value="{{ $aspect_value->value }}"
                                                    name="aspects[{{ $index }}][value]" class="form-control">
                                            </div>
                                            <div class="col">
                                                <div style="height: 100%; display: flex; align-items: end;">
                                                    <button type="button" class="  btn btn-danger"
                                                        onclick="remove_aspect_inputs(event)"> Remove </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="row mb-3">
                                        <div class=" col-6 col-md-8 ">
                                            <label for="aspect_name"> Keys </label>
                                            <input id="aspect_name" type="text"
                                                placeholder="e.g. button, button-up, button up"
                                                name="aspects[0][keys]" class="form-control">
                                        </div>
                                        <div class="col ">
                                            <label for="aspect_name"
                                                title="If you leave its value empty then app will ensure that not to add current Item Specific when given keys match.">
                                                Value </label>
                                            <input id="aspect_name" type="text" 
                                                name="aspects[0][value]" class="form-control">
                                        </div>
                                        <div class="col">
                                            <div style="height: 100%; display: flex; align-items: end;">
                                                <button type="button" class="  btn btn-danger"
                                                    onclick="remove_aspect_inputs(event)"> Remove </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12 mb-3">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-dark" onclick="show_aspect_inputs()"> Add row
                                    </button>
                                    <button type="submit" class="btn btn-dark"> Submit </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        let aspect_quantity = {{ isset($aspect->aspect_values) ? count($aspect->aspect_values) : 0 }};

        function show_aspect_inputs() {
            aspect_quantity++
            let aspect_html = `
                
                <div class="row mb-3 ">
                    <div class="col-6 col-md-8  ">
                        <label> Keys </label>
                        <input  type="text" placeholder="e.g. button, button-up, button up" name="aspects[${aspect_quantity}][keys]" class="form-control">
                    </div>
                    <div class="col ">
                        <label title="If you leave its value empty then app will ensure that not to add current Item Specific when given keys match." > Value  </label>
                        <input  type="text" name="aspects[${aspect_quantity}][value]" class="form-control">
                    </div>
                    <div class="col">
                        <div style="height: 100%; display: flex; align-items: end;">
                            <button type="button" class="  btn btn-danger" onclick="remove_aspect_inputs(event)"> Remove </button>
                        </div>
                    </div>
                </div>
            `;
            $('#inputs_rows').append(aspect_html)
        }

        function remove_aspect_inputs(event) {
            $(event.target).closest('.row').remove();
        }
    </script>
@endsection
