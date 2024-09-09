<?php

namespace App\Jobs;

use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 10800;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $next_page_url = Cache::get('next_page_url', '');
        $shopifyService = new ShopifyService();
        for ($index = 0; $index <= 3; $index++) {

            [$res, $res_headers] =  $shopifyService->get_products(250, '', $next_page_url);
            if (!isset($res['products'])) {
                return;
            }
            $next_page_url = $shopifyService->extract_next_page_link($res_headers);
            Cache::forever('next_page_url', $next_page_url);

            foreach ($res['products'] as $product) {
                dispatch(new EbaySync([$product]));
            }
        }
        // Cache::forget('next_page_url');
    }
}
