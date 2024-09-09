<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EbayController;
use App\Http\Controllers\EbayToShopifyController;
use App\Http\Controllers\FailedProductsController;
use App\Http\Controllers\ItemSpecificsController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ShopifyToEbayController;
use App\Jobs\SyncItems;
use App\Jobs\SyncShopifyToEbayItems;
use App\Jobs\TemplateJob;
use App\Models\ItemSource;
use App\Models\ItemSpecific;
use App\Models\ShopifyProduct;
use App\Services\ChatGPTService;
use App\Services\EbayCategories;
use App\Services\EbayItems;
use App\Services\EbayService;
use App\Services\HelperService;
use App\Services\ShopifyService;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Faker\Extension\Helper;
use Illuminate\Database\Query\IndexHint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $item_sources = ItemSource::where('last_stock', '>', 0)->whereNotIn('ebay_item_id', function ($q) {
        $q->select('ebay_item_id')->from('deleted_ebay_items');
    })->get();
    return view('welcome', compact('item_sources'));
})->middleware('auth');


Route::post('/sync_products', function () {
    SyncItems::dispatch();
    return redirect()->back()->with('message', '1000 item jobs created successfully. Existing item will be skip.');
})->name('sync_products')->middleware('auth');


Route::prefix('shopify-webhooks')->controller(ShopifyToEbayController::class)->group(function () {

    Route::any('/inventory-level-update', 'inventory_level_update');
    Route::any('/product-create-or-update', 'product_create_or_update');
    Route::any('/product-delete', 'product_delete');
    Route::any('/collection-update', 'collection_update');
});
// https://vintage-sync-app.maxenius.com/shopify-webhooks/inventory-level-update
// https://vintage-sync-app.maxenius.com/shopify-webhooks/product-create-or-update
// https://vintage-sync-app.maxenius.com/shopify-webhooks/collection-update
// https://vintage-sync-app.maxenius.com/shopify-webhooks/product-delete


Route::prefix('ebay-webhooks')->controller(EbayToShopifyController::class)->group(function () {

    Route::any('/', 'index');
    Route::any('/testing', 'testing');
    Route::any('/ItemSold', 'ItemSold');
    Route::any('/ItemOutOfStock', 'ItemOutOfStock');
    Route::any('/auction-checkout-complete', 'AuctionCheckoutComplete');
});

// https://vintage-sync-app.maxenius.com/ebay-webhooks/inventory-level-update
// https://vintage-sync-app.maxenius.com/ebay-webhooks/testing
// https://vintage-sync-app.maxenius.com/ebay-webhooks/ItemSold 
// https://vintage-sync-app.maxenius.com/ebay-webhooks/ItemOutOfStock
// https://vintage-sync-app.maxenius.com/ebay-webhooks/auction-checkout-complete

Route::prefix('logs')->controller(LogController::class)->group(function () {
    Route::get('/{log_name}', 'index');
})->middleware('auth');

Route::prefix('ebay-testing-webhook')->group(function () {
    Route::any('/account-deletion', function (Request $request) {

        $challengeCode = @$_GET['challenge_code'];
        $verificationToken = 'jonathan-shopifys-PRD-9f5aec585-d5f763d5';
        $endpoint = 'https://vintage-sync-app.vintageclubmysteryboxsoftware.com/ebay-testing-webhook/account-deletion';

        $hash = hash_init('sha256');
        hash_update($hash, $challengeCode);
        hash_update($hash, $verificationToken);
        hash_update($hash, $endpoint);

        $responseHash = hash_final($hash);
        // echo $responseHash;
        return response()->json([
            'challengeResponse' => $responseHash
        ], 200);
    });
});

// CATEGORY ROUTES
Route::prefix('category')->name('category.')->controller(CategoryController::class)->group(function () {

    Route::get('/', 'index')->name('view');

    Route::post('/mapping', 'mapping')->name('mapping');
})->middleware('auth');

// ITEM SPECIFICS ROUTE
Route::middleware('auth')->prefix('item_specifics')->name('item_specifics.')->controller(ItemSpecificsController::class)->group(function () {
    Route::get('/', 'index')->name('view');
    Route::get('/add-keywords/{aspect_id}', 'add_keywords')->name('add_keywords');

    Route::post('/store-keywords/{aspect_id}', 'store_keywords')->name('store_keywords');
    Route::post('/add-aspect', 'add_aspect')->name('add_aspect');
});

// EBAY ROUTES
Route::middleware('auth')->prefix('ebay')->name('ebay.')->controller(EbayController::class)->group(function () {
    Route::get('/', 'index')->name('view');
    Route::post('/complete-item-update', 'complete_item_update')->name('complete_item_update');
    Route::post('/item-specifics-update', 'item_specifics_update')->name('item_specifics_update');
    Route::post('/title-update', 'title_update')->name('title_update');
});

// SHOPIFY ROUTES
Route::middleware('auth')->prefix('shopify')->name('shopify.')->controller(ShopifyController::class)->group(function () {
    Route::get('/', 'index')->name('view');
    Route::get('/get-store-products', 'get_store_products')->name('get_store_products');

    Route::post('/sync-to-ebay', 'sync_to_ebay')->name('sync_to_ebay');
});

// FAILED PRODUCTS ROUTES
Route::middleware('auth')->prefix('failed-products')->name('failed_products.')->controller(FailedProductsController::class)->group(function (){
    Route::get('/', 'index')->name('view');
    Route::post('/retry-import/{id}', 'retry_import')->name('retry_import');
});

// OAUTH TOKEN ROUTE
Route::get('/get_oauth_token/{name}', function ($name) {
    if ($name == 'dev' || $name == 'developer') {
        echo  HelperService::get_oauth_token();
    }
})->middleware('auth');
Route::any('/testing_route', function (Request $request) {

    // Vintage:Clothing:Women's:Outerwear:Fleece:Checkered:Blue
    // dd($request->text);
    // $ebay_cat = new EbayCategories();
    // $cats =  $ebay_cat->GetSuggestedCategories($request->text);
    // dd($cats);

    // EBAY ITEM CREATING
    // $itemsource = DB::table('failed_ebay_sync_items')->pluck('shopify_product_id')->toArray();
    // dd($itemsource);
    // 8924188311769 remaining
    // SyncShopifyToEbayItems::dispatch(null, ['8924422668505']);
    // dd(' Created ');
    // $products = [];
    // $job = new SyncShopifyToEbayItems();
    // $product = $job->getShopifyProductById('8461462503641');
    // $products[] = $product['product'];
    // $product = $request->all();
    // $item_sources = ItemSource::where('last_stock', '!=', 0)->pluck('ebay_item_id')->toArray();
    // dd($item_sources);

    // $res = HelperService::get_oldest_ebay_items();
    // return $res;
    // $order = $ebayItem->get_order('03-11916-47386');
    // // return $order;
    // $line_items = $order['orders'][0]['lineItems'];
    // foreach ($line_items as $item) {
        
    //     $lineItemId = $item['legacyItemId'];
    //     $sku = $item['sku'];
    //     $quantity = $item['quantity'];
    //     dd($lineItemId, $sku, $quantity);
    // }

    // $chatGPT = new ChatGPTService();
    // $output = $chatGPT->item_used_condition($product);
    // dd($output);
    // echo '<pre>';
    // print_r($title);
    // echo '</pre>';

    // $itemSources = ItemSource::where('template_applied', '0')->get();
    // foreach ($itemSources as  $key =>  $item) {
    //     dispatch(new SyncShopifyToEbayItems(null, [strval($item->shopify_product_id)]));
    // }


    // $shopifyService = new ShopifyService();
    // [$res, $res_headers] =  $shopifyService->get_products(250);
    // return $res;
    // $updated_item_specfics = [];
    // $itemspecifics = ItemSpecific::all()->toArray();
    // foreach ($itemspecifics as $aspect) {
    //     $updated_item_specfics[$aspect['aspect_name']] = json_decode($aspect['aspect_values']);
    // }
    // return ($updated_item_specfics);

    // $content = $request->getContent();
    // $xml = simplexml_load_string($content, null, 0, 'soapenv', true);
    // $response = $xml->xpath('//soapenv:Body')[0];
    // $ebayItem = new EbayItems();
    // $item_IDs = $request->all();
    // foreach ($item_IDs as $item_ID) {
    //     $itemSource = ItemSource::where('ebay_item_id', strval($item_ID))->first();
    //     $shopify_product_id = (string) $itemSource->shopify_product_id;
    //     $shopifyProduct = ShopifyProduct::find($shopify_product_id);
    //     $old_price = round($shopifyProduct->price) - 0.01;
    //     $request_xml = <<<XML
    //         <StartPrice>$old_price</StartPrice>
    //     XML;
    //     $response = $ebayItem->update_item_fields($request_xml, $item_ID);
    // }
    
    // return json_encode(EbayService::FindingService(count:100, days:14, nextdays:7));
    // return Carbon::now()->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');


});

Auth::routes();

Route::get('/home', function (){
    return redirect('/');
})->name('home');
