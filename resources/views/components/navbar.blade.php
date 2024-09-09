<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
      <a class="navbar-brand" href="/"> {{ config('app.name') }} </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="/"> Home </a>
          </li>
          {{-- <li class="nav-item">
            <a class="nav-link" aria-current="page" href="{{ route('category.view') }}"> Category </a>
          </li> --}}
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="{{ route('item_specifics.view') }}"> Item Specfics </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="{{ route('ebay.view') }}"> Ebay </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="{{ route('shopify.view') }}"> Shopify </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="{{ route('failed_products.view') }}"> Failed Products </a>
          </li>
      </div>
    </div>
  </nav>