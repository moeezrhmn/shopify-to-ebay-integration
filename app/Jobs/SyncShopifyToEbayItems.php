<?php

namespace App\Jobs;

use App\Models\ItemSource;
use App\Services\EbayItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncShopifyToEbayItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shopify_access_token;
    protected $import_endpoint;
    protected $shopify_store;
    protected $store_url;
    protected $request_url;
    protected $limit = 100;
    public $timeout = 10800;
    protected $EbayItems;

    protected $nextPageLink;
    protected $retry;

    /**
     * Create a new job instance.
     */
    public function __construct($nextPageLink = null, $retry = [])
    {
        $this->nextPageLink = $nextPageLink;
        $this->retry = $retry;

        $this->EbayItems = new EbayItems();
        $this->shopify_access_token = env("SHOPIFY_ACCESS_TOKEN", "");
        $this->shopify_store = env("SHOPIFY_STORE", "");
        // Etsy collection id = 392731820249
        $this->import_endpoint = '/admin/api/2024-01/products.json?published_status=published&collection_id=392731820249&limit='. $this->limit . '&order=created_at desc';
        $this->store_url = "https://" . $this->shopify_store;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $headers = ['X-Shopify-Access-Token' => $this->shopify_access_token];
        $this->request_url = empty($this->nextPageLink) ? ($this->store_url . $this->import_endpoint) : $this->nextPageLink;            
        
        $product_added = 0;
        $jump_items = 0;

        while (true) {
            
            $response = null;
            $res_headers = null;
            $products = [];
            if(empty($this->retry)){

                if(empty($this->request_url)){
                    Log::channel('sync_products')->info(' Request NULL: '. $this->request_url);
                    break;
                }
                $response = Http::withHeaders($headers)->get($this->request_url);
                $res_headers = $response->headers();
                $response = $response->json();
                // Log::channel('sync_products')->info('List down items: '. print_r($response, true));
                // return;
                //  extract next page URL from Headers Array.
                $this->request_url = $this->extract_next_page_link($res_headers);

                if (!isset($response['products'])) {
                    Log::channel('sync_products')->error($response['errors'] ?? 'Unknown error occurred.  ( No product found in API response ) ');
                    break;
                }
                $products = $response['products'];

            } else {
                $this->request_url = '';
                foreach ($this->retry as $shopify_product_id) {   
                    $failed_prod = DB::table('failed_ebay_sync_items')->where('shopify_product_id', strval($shopify_product_id))->first();
                    if(!is_null($failed_prod)){
                        if((int) $failed_prod->tried >= 5){
                            continue;
                        }
                        DB::table('failed_ebay_sync_items')
                        ->where('shopify_product_id', strval($shopify_product_id))
                        ->update(['tried' => (int) $failed_prod->tried + 1]);
                    }
                    
                    $product = $this->getShopifyProductById($shopify_product_id);
                    $products[] = $product['product'];
                    
                }
                $this->retry = [];
            }
            Log::channel('sync_products')->info('Products Count: ' . count($products));

            if(empty($products)){
                Log::channel('sync_products')->error('Import request from shopfy to ebay: Product not found!');
                break;
            }
            $iterator = 0;
            foreach ($products as  $product) {
                $productArr = [];
                $productArr[] = $product;
                // $iterator++;
                // if( $iterator <= $jump_items){
                //     continue;
                // }
                Log::channel('sync_products')->info('__________________________________________________________________________________');
                Log::channel('sync_products')->info('----------------------------------------------------------------------------------');

                try {
                    $checkItem = ItemSource::where('shopify_product_id', strval($product['id']))->first();
                    if($checkItem){
                        // $this->EbayItems->update($product, $checkItem->ebay_item_id);
                        // $this->EbayItems->update_item_title($product, $checkItem->ebay_item_id);
                        // $this->EbayItems->update_item_specifics($product, $checkItem->ebay_item_id);
                        TemplateJob::dispatch([$checkItem->ebay_item_id]);
                        Log::channel('sync_products')->info('importing products: item already exist. TemplateJOb created. ID: '. $product['id']);
                        continue;
                    }
                    if( (float) $product['variants'][0]['price'] == 0 || (int) $product['variants'][0]['inventory_quantity'] == 0 ){
                        Log::channel('sync_products')->info('importing products: Product has price 0 or quntity 0.  ID: '. $product['id']);
                        continue;
                    }
                    $itemIDs = $this->EbayItems->insert($productArr);
                    if($itemIDs && count($itemIDs) > 0){
                        $product_added = $product_added + 1; 
                        $this->store($productArr, $itemIDs);
                        TemplateJob::dispatch($itemIDs);
                    }
                    Log::channel('sync_products')->info("Successfully imported products  -- Imported products batch added: $product_added  Limit of each batch " . $this->limit );
                } catch (\Throwable $th) {
                    $err = $th->getMessage(). " in file " . $th->getFile() . " on line " . $th->getLine();
                    $this->EbayItems->store_failed_sync($product['id'], ['error' => $err ]);
                    Log::channel('sync_products')->error("Error : " . $err );
                }
                // break;
            }
            break;
        }
        Log::channel('sync_products')->info("Products processed in 1 request with limit -> " . $this->limit );
    }

    /**
     * Extract product image url
     */
    protected function extract_img_url($product) {
        return isset($product['image']['src']) ? $product['image']['src'] : (isset($product['images'][0]['src']) ? $product['images'][0]['src'] : '');
    }

     /**
     * Extract next page link.
     */
    protected function extract_next_page_link($res_headers){
        $link = null;
        if (isset($res_headers['link'])) {
            $link_header = $res_headers['link'][0];
            if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
                Log::channel('sync_products')->info(" Matches :: $matches[1]");
                $link = $matches[1];
            }
        }
        if(!empty($link)){
            DB::table('pending_requests')->insert([
                'request_url' => $link,
                'number_of_items' => $this->limit
            ]);
        }
        return $link;
    }

     /**
     * Escaped product values
     */
    protected function escaped_values($product){
        $escapedValues = [];
        foreach ($product as  $prod) {
            if (is_numeric($prod)) {
                $escapedValues[] = (int) $prod;
            } else {
                $escapedValues[] = "'" . addslashes($prod) . "'";
            }
        }
        return $escapedValues;
    }


    protected function store($products, $itemIDs){
        $item_id = reset($itemIDs);
        $sources = [];
        foreach ($products as $product) {
            $sources[] = [
                'item_sku' => $product['variants'][0]['sku'],
                'shopify_product_id' => strval($product['id']),
                'ebay_item_id' => strval($item_id),
                'inventory_item_id' => strval($product['variants'][0]['inventory_item_id']),
                'last_stock' => $product['variants'][0]['inventory_quantity'],
                'created_at' => now()
            ];
        }
        try {
            foreach ($sources as $source) {
                ItemSource::updateOrInsert([
                    'shopify_product_id' => $source['shopify_product_id']
                ], $source);
            }
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('ERROR Storing listed item ' . $th->getMessage());
        }
    }


    public function getShopifyProductById($productId)
    {
        $url = "https://{$this->shopify_store}/admin/api/2023-01/products/{$productId}.json";
        $headers = ['X-Shopify-Access-Token' => $this->shopify_access_token];
        try {
            $response = Http::withHeaders($headers)->get($url);
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error("Shopify API request error: Status code {$response->status()} - {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::error("Shopify API request error: {$e->getMessage()}");
        }
        return null;
    }

}
