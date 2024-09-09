<?php

namespace App\Http\Controllers;

use App\Jobs\TemplateJob;
use App\Models\FailedEbaySyncItem;
use App\Models\ItemSource;
use App\Services\EbayItems;
use App\Services\HelperService;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FailedProductsController extends Controller
{
    public function index(){
        $failed_products = FailedEbaySyncItem::whereNotIn('shopify_product_id', function($query) {
            $query->select('shopify_product_id')->from('item_sources');
        })->get();
        return view('failed_products.view', compact('failed_products'));
    }


    public function retry_import($id){
        $shopifyService = new ShopifyService();
        $product = $shopifyService->get_single_product($id);

        if( !$product || !isset($product['product']) || !$product['product']){
            return response()->json([
                'status' => false,
                'message' => 'Could not fetch item from shopify! ',
            ]);
        }

        $product = $product['product'];
        $ebayItems = new EbayItems();
        $item_IDs = $ebayItems->insert([$product]);

        if(empty($item_IDs)){
            return response()->json([
                'status' => false,
                'message' => HelperService::addItems_last_error_msg(),
            ]);
        }

        ItemSource::store_item($product, reset($item_IDs));
        dispatch(new TemplateJob($item_IDs));

        return response()->json([
            'status' => true,
            'message' => 'Successfully item imported!'
        ]);
    }
}
