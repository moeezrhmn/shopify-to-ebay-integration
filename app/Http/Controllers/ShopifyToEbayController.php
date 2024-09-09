<?php

namespace App\Http\Controllers;

use App\Jobs\EbaySync;
use App\Models\ItemSource;
use App\Models\ItemSpecific;
use App\Models\ShopifyProduct;
use App\Services\ChatGPTService;
use App\Services\EbayItems;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyToEbayController extends Controller
{
    protected $chatGPTService;
    protected $ebay_url;
    protected $ebayItems;

    public function __construct(ChatGPTService $chatGPTService)
    {
        $this->chatGPTService = $chatGPTService;
        $this->ebay_url = env('EBAY_ENDPOINT');
        $this->ebayItems = new EbayItems();
    }

    /**
     * Run when a product quantity update
     */
    public function inventory_level_update(Request $request)
    {
        $inventory_item_id =  strval($request->inventory_item_id);
        $available = (int) $request->available;
        if ($available < 0) $available = 0;

        $itemSource = ItemSource::where('inventory_item_id', $inventory_item_id)->first();
        if (empty($itemSource)) {
            Log::channel('shopify_webhook')->error('Item Stock Update: Item not found in database!');
            return;
        }
        $shopifyProduct = ShopifyProduct::where('inventory_item_id', strval($inventory_item_id))->first();
        if($shopifyProduct){
            $shopifyProduct->inventory_quantity = $available;
            $shopifyProduct->save();
        }

        try {
            $ebay_item_id = $itemSource->ebay_item_id;
            $shopify_product_id = $itemSource->shopify_product_id;
            $update_stock_res = $this->ebayItems->update_item_stock($available, $ebay_item_id);

            Log::channel('shopify_webhook')->error('Item Stock Update: Update ebay Stock: ' . print_r($update_stock_res, true));

            Log::channel('shopify_webhook')->error('Item Stock Update: New stock: ' . $available . ' Last stock: ' . $itemSource->last_stock);
            $itemSource->last_stock = $available;
            $itemSource->save();

            $response = $this->ebayItems->EndItem($ebay_item_id);

            Log::channel('shopify_webhook')->info("Item Stock Update: Item stock updated on ebay. shopify_product_id: $shopify_product_id, new quantity: $available, ebay_item_id: $ebay_item_id , EndItem response:  " . print_r($response, true));
        } catch (\Throwable $th) {
            Log::channel('shopify_webhook')->error('Item Stock Update : ' . $th->getMessage());
        }
    }

    /**
     * Run when a product created 
     */
    public function product_create_or_update(Request $request)
    {
        $product = $request->all();
        try {
            $shopifyService = new ShopifyService();
            $productInCollection = $shopifyService->is_product_in_collection($product['id']);
            if(!$productInCollection){
                return;
            };
            Log::channel('shopify_webhook')->info('[product_create_or_update] Called and exist in etsy collection.');
            
            ShopifyProduct::store_product($product);
            $ebayItems = $this->ebayItems;
            $itemSource = ItemSource::where('shopify_product_id', strval($product['id']))->first();
            if($itemSource){
                $ebayItems->update($product, $itemSource->ebay_item_id);
            }else{
                $itemIDs = $ebayItems->insert([$product]);
                $this->save([$product], $itemIDs);
            }
            Log::channel('shopify_webhook')->info('[product_create_or_update] successfully saved!');
        } catch (\Throwable $th) {
            Log::channel('shopify_webhook')->error('Product Creation: ' . $th->getMessage());
        }
    }

    public function product_delete(Request $request)
    {
        $product_id = $request->id;
        $itemSource = ItemSource::where('shopify_product_id', strval($product_id))->first();
        if(!$itemSource){
            Log::channel('shopify_webhook')->info('[product_delete] called but not in ItemSource');
            return;
        }
        try {
            
            $ebay_item_id = $itemSource->ebay_item_id;
            $itemSource->last_stock = 0;
            $itemSource->save();
            $end_items_reponse = $this->ebayItems->EndItem($ebay_item_id);
            $shopifyProduct = ShopifyProduct::find(strval($product_id));
            if($shopifyProduct) $shopifyProduct->delete();
            Log::channel('shopify_webhook')->info("product_delete: product ended and deleted successfully. ShopifyID:$product_id ebayItemID: $ebay_item_id EndItem response: $end_items_reponse");
        
            
        } catch (\Throwable $th) {
            Log::channel('shopify_webhook')->info("product_delete: Error occured. ShopifyID:$product_id ebayItemID: $ebay_item_id Error:  " . $th->getMessage());
        }
        

    }

    public function collection_update(Request $request)
    {
        $collection = $request->all();
        if(!isset($collection['handle'])) return abort(404);

        $handle = $collection['handle'];
        if ($handle != 'etsy'){
            Log::channel('shopify_webhook')->info('[collection_update] called but not Etsy collection.');
            return;
        }
        $shopifyService = new ShopifyService();
        $ebayItems = new EbayItems();

        $stored_IDs = ShopifyProduct::pluck('shopify_product_id')->toArray();
        $current_IDs = $shopifyService->get_all_collection_products_ids();

        $removed_products = array_diff($stored_IDs, $current_IDs);
        $added_products = array_diff($current_IDs, $stored_IDs);
        Log::channel('shopify_webhook')->info('[collection_update]  Removed Products: ' . print_r($removed_products, true));
        Log::channel('shopify_webhook')->info('[collection_update]  added Products ' . print_r($added_products, true));

        try {

            foreach ($removed_products as  $shopify_product_id) {

                $shopifyProduct = ShopifyProduct::find(strval($shopify_product_id));
                if ($shopifyProduct) $shopifyProduct->delete();

                $itemSource = ItemSource::where('shopify_product_id', strval($shopify_product_id))->first();
                if ($itemSource) {
                    $ebay_item_id = $itemSource->ebay_item_id;
                    $itemSource->last_stock = 0;
                    $itemSource->save();
                    $res = $ebayItems->EndItem($ebay_item_id);
                    Log::channel('shopify_webhook')->info('[collection_update] EndItem reponse: ' . $res);
                }
            }

            foreach ($added_products as  $shopify_product_id) {

                $product = $shopifyService->get_single_product($shopify_product_id);
                if($product && isset($product['product'])){

                    ShopifyProduct::store_product($product['product']);

                    $itemSource = ItemSource::where('shopify_product_id', strval($shopify_product_id))->first();
                    if (!$itemSource) {

                        dispatch(new EbaySync([$product['product']]));
                        Log::channel('shopify_webhook')->info('[collection_update] Item creation job dispatched ID: ' . $shopify_product_id);
                    }
                }
                
            }
        } catch (\Throwable $th) {
            Log::channel('shopify_webhook')->info('[collection_update] Error: ' . $th->getMessage());
        }
    }

    protected function save($products, $itemIDs)
    {
        $item_id = reset($itemIDs);
        foreach ($products as $product) {
            $item = [
                'item_sku' => $product['variants'][0]['sku'],
                'shopify_product_id' => strval($product['id']),
                'ebay_item_id' => strval($item_id),
                'inventory_item_id' => strval($product['variants'][0]['inventory_item_id']),
                'last_stock' => $product['variants'][0]['inventory_quantity'],
                'created_at' => now()
            ];
            try {
                ItemSource::updateOrInsert(
                    ['item_sku' => $item['item_sku']],
                    $item
                );
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
    }
}
