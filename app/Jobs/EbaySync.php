<?php

namespace App\Jobs;

use App\Models\ItemSource;
use App\Services\EbayItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EbaySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $EbayItems;
    protected $products;
    /**
     * Create a new job instance.
     */

    public function __construct($products = [])
    {
        $this->products = $products;
        $this->EbayItems = new EbayItems();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $products = $this->products;
        if (empty($products)) {
            Log::channel('sync_products')->error('Import request from shopfy to ebay: Product not found!');
        }
        foreach ($products as  $product) {

            Log::channel('sync_products')->info('__________________________________________________________________________________');
            Log::channel('sync_products')->info('----------------------------------------------------------------------------------');

            try {
                $checkItem = ItemSource::where('shopify_product_id', strval($product['id']))->first();
                if ($checkItem) {
                    // $this->EbayItems->update($product, $checkItem->ebay_item_id);
                    // $this->EbayItems->update_item_title($product, $checkItem->ebay_item_id);
                    // $this->EbayItems->update_item_specifics($product, $checkItem->ebay_item_id);
                    if($checkItem->template_applied == 0){
                        TemplateJob::dispatch([$checkItem->ebay_item_id]);
                    }
                    Log::channel('sync_products')->info('Importing product: Item Exist skipping. ID: ' . $product['id']);
                    continue;
                }

                if ((float) $product['variants'][0]['price'] == 0 || (int) $product['variants'][0]['inventory_quantity'] == 0) {
                    Log::channel('sync_products')->info('Importing product: Product has price 0 or quntity 0.  ID: ' . $product['id']);
                    continue;
                }
                $itemIDs = $this->EbayItems->insert([$product]);
                if ($itemIDs && count($itemIDs) > 0) {
                    $item_id = reset($itemIDs);
                    $this->store($product, $item_id);
                    TemplateJob::dispatch([$item_id]);
                }
            } catch (\Throwable $th) {
                $err = $th->getMessage() . " in file " . $th->getFile() . " on line " . $th->getLine();
                $this->EbayItems->store_failed_sync($product['id'], ['error' => $err]);
                Log::channel('sync_products')->error("Error : " . $err);
            }
        }
    }

    protected function store($product, $item_id)
    {
        $source = [
            'item_sku' => $product['variants'][0]['sku'],
            'shopify_product_id' => strval($product['id']),
            'ebay_item_id' => strval($item_id),
            'inventory_item_id' => strval($product['variants'][0]['inventory_item_id']),
            'last_stock' => $product['variants'][0]['inventory_quantity'],
            'created_at' => now()
        ];

        try {
            ItemSource::insert($source);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('ERROR Storing listed item ' . $th->getMessage() .  " \n\n  data: " . print_r($source, true));
        }
    }
}
