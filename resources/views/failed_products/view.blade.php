@extends('layouts.app')

@section('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
@endsection

@section('content')
    <div class=" d-flex align-items-start pt-5" style="min-height: 90vh;">
        <div class="container pb-5 mb-5 ">
            <div class="row mb-3">
                <div class="col">
                    <h3> Failed Products </h3>
                </div>

            </div>
            <div class="row">
                <div class="col">
                    <div class="p-3 border rounded">
                        <table class="table" id="failed_products_table">
                            <thead>
                                <th> Shopify Product ID </th>
                                <th> Errors </th>
                                <th> Tried </th>
                                <th> Actions </th>
                            </thead>
                            <tbody>
                                @foreach ($failed_products as $f_product)
                                    <tr>
                                        <td> {{ $f_product->shopify_product_id }} </td>
                                        <td> {{ App\Services\HelperService::parse_failed_prod_errors($f_product->errors) }}
                                        </td>
                                        <td> {{ $f_product->tried }} </td>
                                        <td> <button
                                                onclick="retry_import('{{ route('failed_products.retry_import', ['id' => $f_product->shopify_product_id]) }}', event)"
                                                class=" btn btn-sm btn-dark"> Retry </button> </td>
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
        const table = new DataTable('#failed_products_table', {
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

        const Toast = Swal.mixin({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });

        async function retry_import(url, event) {
            let confirmation = await Swal.fire({
                title: "Do you realy want to retry?",
                showCancelButton: true,
                confirmButtonText: "Yes",
            }).then((result) => {
                if (result.isConfirmed) {
                    return true;
                }
                return false;
            });
            if (!confirmation) return;

            $(event.target).html('<i class="fa fa-spinner fa-spin"></i> Retry');

            $.ajax({
                'url': url,
                'type': 'POST',
                'headers': {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                'success': (res) => {
                    $(event.target).html('Retry')
                    console.log(res);
                    if (res.status) {
                        $(event.target).closest('tr').fadeOut();
                        Toast.fire({
                            icon: "success",
                            title: res?.message || 'Product Imprted'
                        });
                    } else {
                        Toast.fire({
                            icon: "error",
                            title: res?.message || 'Internal server error!'
                        });
                    }
                },
                'error': (err) => {
                    $(event.target).html('Retry')
                    Toast.fire({
                        icon: "error",
                        title: err?.message || 'Internal server error!'
                    });
                }
            });
        }
    </script>
@endsection
