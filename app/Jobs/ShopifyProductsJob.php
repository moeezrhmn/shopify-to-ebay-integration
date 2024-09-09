<?php

namespace App\Jobs;

use App\Models\ShopifyProduct;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ShopifyProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 10800;
    protected $shopifyService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->shopifyService = new ShopifyService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $next_page_url = '';
        do {
            try {

                [$products, $res_headers] = $this->shopifyService->get_products(250, '', $next_page_url);
                foreach ($products['products'] as  $product) {
                    try {
                        ShopifyProduct::store_product($product);
                    } catch (\Throwable $th) {
                        Log::channel('sync_products')->error('[ShopifyPorductsJob] Single Prodcut Error: ' . $th->getMessage());
                    }
                }

                $next_page_url = $this->shopifyService->extract_next_page_link($res_headers);
            } catch (\Throwable $th) {
                Log::channel('sync_products')->error('[ShopifyPorductsJob] Error: ' . $th->getMessage());
            }
            if (empty($products)) {
                Log::channel('sync_products')->error('[ShopifyPorductsJob] No products found in response!');
            }
        } while ($next_page_url);
    }
}
