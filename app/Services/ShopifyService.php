<?php

namespace App\Services;

use Error;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $access_token;
    protected $store;
    protected $store_url;

    public function __construct()
    {
        $this->access_token = env("SHOPIFY_ACCESS_TOKEN", "");
        $this->store = env("SHOPIFY_STORE", "");
        $this->store_url = "https://" . $this->store;
    }

    public function get_single_product($product_id)
    {

        $url = "{$this->store_url}/admin/api/2023-01/products/{$product_id}.json";
        $headers = ['X-Shopify-Access-Token' => $this->access_token];

        try {
            $response = Http::withHeaders($headers)->get($url);
            if ($response->successful()) {
                return $response->json();
            } else {
                Log::channel('shopify_service')->error("Get Product Error: Status code {$response->status()} - {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::error("Get Product Error: {$e->getMessage()}");
        }
        return null;
    }


    public function get_products($limit = 20, $params = '', $next_page_url = '')
    {
        $url = "{$this->store_url}/admin/api/2024-01/products.json?published_status=published&collection_id=392731820249&order=created_at desc&limit=$limit" . $params;

        if ($next_page_url) $url = $next_page_url;

        $headers = ['X-Shopify-Access-Token' => $this->access_token];

        $response = Http::withHeaders($headers)->timeout(60)->get($url);
        $res_headers = $response->headers();
        $response = $response->json();

        return [$response, $res_headers];
    }

    public function get_count($params){
        $url = "{$this->store_url}/admin/api/2024-07/products/count.json?published_status=published&collection_id=392731820249" . $params;
        
        $headers = ['X-Shopify-Access-Token' => $this->access_token];
        $response = Http::withHeaders($headers)->timeout(60)->get($url);
        $response = $response->json();

        return $response;
    }

    public function extract_all_images($product){
        $images = $product['images'];
        if(empty($images)){
            $image = $product['image']['src'];
            return $image ? [$image] : [];
        }
        $all_images = [];
        foreach ($images as  $image) {
            $all_images[] = $image['src'];
        }
        return $all_images;
    }
    public function extract_next_page_link($res_headers)
    {
        $link = null;
        if (isset($res_headers['link'])) {
            $link_header = $res_headers['link'][0];
            if (preg_match('/<([^>]+)>;\s*rel="next"/', $link_header, $matches)) {
                Log::channel('shopify_service')->info(" Extracted Next Page Link :: $matches[1]");
                $link = $matches[1];
            }
        }
        return $link;
    }


    public function update_inventory($inventory_item_id, $new_stock)
    {

        $headers = ['X-Shopify-Access-Token' => $this->access_token];

        try {
            $response = Http::withHeaders($headers)->timeout(60)->get($this->store_url . '/admin/api/2024-04/inventory_levels.json?inventory_item_ids=' . $inventory_item_id);
            $response = $response->json();

            
            if(isset($response['errors'])){
                throw new Error(implode( ', ', $response['errors']));
            }
            $inventory_level = reset($response['inventory_levels']);
            // $available_quantity = (int) $inventory_level['available'];
            
            $update_inventory_payload = [
                "location_id" => $inventory_level['location_id'],
                "inventory_item_id" => $inventory_item_id,
                "available_adjustment" => $new_stock,
            ];
            
            // https://vintageclub.uk/admin/api/2024-04/inventory_levels/adjust.json
            // https://vintage-club-uk.myshopify.com/admin/api/2024-04/inventory_levels/adjust.json

            $request_url = 'https://vintage-club-uk.myshopify.com/admin/api/2024-04/inventory_levels/adjust.json';
            $response = Http::withHeaders($headers)->timeout(60)->post($request_url, $update_inventory_payload);
            $response = $response->json();
            if(isset($response['errors'])){
                throw new Error(implode( ', ', $response['errors']));
            }
            return $response;
        } catch (\Throwable $th) {
            Log::channel('ebay_webhook')->info('Error: '. $th->getMessage());
            throw new Error($th->getMessage());
        }
    }

    public function get_all_collection_products_ids(){
        $next_page_url = '';
        $all_IDs = [];
        do {

            [$products, $res_headers] = $this->get_products(250, '&fields=id', $next_page_url);
            $products = $products['products'];

            $all_IDs = array_merge($all_IDs, array_column($products, 'id'));

            $next_page_url = $this->extract_next_page_link($res_headers);
        } while ($next_page_url);

        return $all_IDs;
    }

    public function is_product_in_collection($product_id, $collection_id = '392731820249') {

        $request_url = "{$this->store_url}/admin/api/2024-04/products.json?status=active&published_status=published&collection_id= {$collection_id}&ids={$product_id}";
        $headers = ["X-Shopify-Access-Token:" . $this->access_token];

        try {
            $response = Http::withHeaders($headers)->get($request_url);
            if ($response->successful()) {
                return $response->json();
            }else{
                Log::channel('shopify_service')->error('[is_product_in_collection] response not successfull: ' . $response->json());
                return null;
            }
        } catch (\Throwable $th) {
            Log::channel('shopify_service')->error('[is_product_in_collection] Error: ' . $th->getMessage());
        }
        return null;
    }
}
