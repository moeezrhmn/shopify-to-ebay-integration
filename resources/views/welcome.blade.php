@extends('layouts.app')

@section('content')
    <div class=" d-flex align-items-start pt-5" style="min-height: 90vh;">
        <div class="container pb-5 mb-5 ">
            <div class="row mb-3">
                <div class="col">
                    <h3> Shopify to eBay Syncronized Products </h3>
                </div>

            </div>
            <div class="row">
                <div class="col">
                    <div class="p-3 border rounded">
                        <table class="table" id="item_source_table">
                            <thead>
                                <th> Shopify Product ID </th>
                                <th> Inventory Item ID </th>
                                <th> Ebay Item ID </th>
                                <th> Last Stock </th>
                                <th> Template Applied </th>
                            </thead>
                            <tbody>
                                @foreach ($item_sources as $item)
                                    <tr>
                                        <td> {{ $item->shopify_product_id }} </td>
                                        <td> {{ $item->inventory_item_id }} </td>
                                        <td> {{ $item->ebay_item_id }} </td>
                                        <td> {{ $item->last_stock }} </td>
                                        <td> 
                                            <span class="badge text-bg-{{ $item->template_applied ? 'success' : 'danger' }} ">
                                                {{ $item->template_applied ? 'Applied' : 'Not Applied' }}
                                            </span>
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


@endsection

@section('foot')
    {{-- Sweet alerts 2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- Bootstrap Datatables --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.2/js/dataTables.bootstrap5.js"></script>

    <script>
        const table = new DataTable('#item_source_table', {
            autoWidth: false,
            columnDefs: [{
                    orderable: false,
                    targets: 0
                },
                {
                    orderable: false,
                    targets: 1
                },
                {
                    orderable: false,
                    targets: 2
                },
            ]
        });

    </script>
@endsection
