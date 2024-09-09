@extends('layouts.app')

@section('head')
    {{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.2/css/dataTables.bootstrap5.css"> --}}
@endsection

@section('content')
    <div class=" d-flex align-items-start pt-5" style="min-height: 90vh;">
        <div class="container pb-5 mb-5 ">
            <div class="row mb-3">
                <h2>Shopify</h2>
            </div>

            <div class="row  ">
                <div class="col-12 mb-5 ">
                    <form action="{{ route('shopify.get_store_products') }}"
                        class="border row align-items-end rounded p-3 bg-light" method="get">
                        <div class="col mb-3">
                            <label for="created_at_min"> Created At Minimum (From) : </label>
                            <input type="datetime-local" name="created_at_min" id="created_at_min" class="form-control">
                        </div>
                        <div class="col mb-3">
                            <label for="created_at_max"> Created At Maximum (To) : </label>
                            <input type="datetime-local" name="created_at_max" id="created_at_max" class="form-control">
                        </div>
                        <div class="col mb-3">
                            <button type="submit" class="btn btn-dark"> Submit </button>
                        </div>
                    </form>

                    @if (isset($products))
                        <div class="row mt-5 ">
                            <div class="col-6 mb-3 ">
                                @if (isset($total_count['count']))
                                    <h6> Total: {{ $total_count['count'] }} </h6>
                                @endif
                            </div>
                            <div class="col-6 mb-3">
                                <div style="display: flex; justify-content: end;">
                                    <!-- <button class="btn btn-dark " onclick="multiple_sync(event)">Sync Selected</button> -->
                                </div>
                            </div>
                            <div class="col-12">
                                <table id="products-table" class="table" style="width: 100%">
                                    <thead>
                                        <th> <input type="checkbox" id="select-all"> </th>
                                        <th> Product </th>
                                        <th> Name </th>
                                        <th> Vendor </th>
                                        <th> Product Type </th>
                                        <th> Created at </th>
                                        <th> Actions </th>
                                    </thead>

                                    <tbody>
                                        @foreach ($products['products'] as $product)
                                            <tr>
                                                <td><input type="checkbox" data-shopify_product_id="{{ $product['id'] }}"
                                                        class="row-select"></td>
                                                <td> <img style="width:150px; "
                                                        src="{{ str_replace('.jpg?v', '_300x300.jpg?v', \App\Services\HelperService::extract_img_url($product)) }}"
                                                        alt="">
                                                </td>
                                                <td>
                                                    <div style="word-wrap: break-word;"> {{ $product['title'] }} </div>
                                                    <div
                                                        style="font-size: 14px; display: flex; align-items: center; gap:5px; flex-wrap: wrap; ">
                                                        <div> <b> Qty: </b>
                                                            {{ $product['variants'][0]['inventory_quantity'] }} </div>
                                                        <div> <b> Price: </b> {{ $product['variants'][0]['price'] }} </div>
                                                        <div> <b> SKU: </b> {{ $product['variants'][0]['sku'] }} </div>
                                                        <div> <b> ID: </b>{{ $product['id'] }} </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    {{ $product['vendor'] }}
                                                </td>
                                                <td>
                                                    {{ $product['product_type'] }}
                                                </td>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($product['created_at'])->format('d M Y, h:i A') }}
                                                </td>
                                                <td>
                                                    @if ($ebay_item_id = \App\Services\HelperService::is_item_in_ebay($product['id']))
                                                        <a target="_blank"
                                                            href="https://www.ebay.com/itm/{{ $ebay_item_id }}">View</a>
                                                    @else
                                                        <!-- <button onclick="single_sync(event, {{ $product['id'] }})"
                                                            class="btn btn-dark btn-sm"> Sync </button> -->
                                                        <span class="badge text-bg-danger" > Not Imported </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>




        </div>
    </div>
@endsection

@section('foot')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.2/js/dataTables.bootstrap5.js"></script>

    <script>
        const table = new DataTable('#products-table', {
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
                    targets: 6
                },
            ]
        });

        $('#select-all').on('click', function() {

            var rows = table.rows({
                'search': 'applied'
            }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
        });
        $('#products-table tbody').on('change', 'input[type="checkbox"]', function() {
            // If any checkbox is not checked, uncheck the select-all checkbox
            if (!this.checked) {
                var el = $('#select-all').get(0);
                if (el && el.checked && ('indeterminate' in el)) {
                    el.indeterminate = true;
                }
            }
        });

        function multiple_sync(e) {
            $(e.target).attr('disabled', 'disabled');
            let ids = [];
            var rows = table.rows({
                'search': 'applied'
            }).nodes();
            $('input[type="checkbox"]:checked', rows).each((index, checkboxEle) => {
                ids.push($(checkboxEle).data('shopify_product_id'));   
            });
            // console.log('all selected ids ', ids)
            handle_sync_request(e.target, ids)
        }
        
        function single_sync(e, shopify_product_id) {
            $(e.target).attr('disabled', 'disabled');
            handle_sync_request(e.target, [shopify_product_id])
        }

        function handle_sync_request(element = null, shopify_product_ids = []) {
            $.ajax({
                'url': "{{ route('shopify.sync_to_ebay') }}",
                'method': 'POST',
                'data': {
                    ids: shopify_product_ids
                },
                success: (res) => {
                    console.log(res);
                    if (res.status) {
                        $(element).replaceWith('<span style="font-size:20px;" class="badge bg-info">Request Submitted</span>');

                    }
                }
            })
        }
    </script>
@endsection
