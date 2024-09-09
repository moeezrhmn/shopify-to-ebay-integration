<?php

use App\Jobs\EbaySync;
use App\Jobs\ShopifyProductsJob;
use App\Jobs\SyncShopifyToEbayItems;
use App\Models\DeletedEbayItem;
use App\Models\FailedEbaySyncItem;
use App\Models\ItemSource;
use App\Models\SaleItem;
use App\Services\EbayItems;
use App\Services\EbayService;
use App\Services\HelperService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Psy\CodeCleaner\AssignThisVariablePass;

$limit = 2100;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->daily();

Artisan::command('run_pending_requests', function () {
    try {

        $pending_request = DB::table('pending_requests')->first();
        if ($pending_request) {
            $request_url = $pending_request->request_url;
            $id = $pending_request->id;
            DB::table('pending_requests')->where('id', $id)->delete();

            dispatch(new SyncShopifyToEbayItems($request_url));

            Log::channel('cron_jobs')->info('Cron job [command: run_pending_requests] SUCCESSFULLY RUN PENDING REQUEST FOUND.');
        } else {
            Log::channel('cron_jobs')->info('Cron job [command: run_pending_requests] NO PENDING REQUEST FOUND!');
        }
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->info('Cron job [command: run_pending_requests] ERROR: ' . $th->getMessage());
    }
});


Artisan::command('run_sync', function () {
    if ($this->confirm('Do you wish to continue running the sync?')) {
        try {
            SyncShopifyToEbayItems::dispatch();
            $this->comment('Sync has been run successfully.');
        } catch (\Throwable $th) {
            $this->comment("Error: " . $th->getMessage());
        }
    } else {
        $this->comment('Sync cancelled.');
    }
});

Artisan::command('run-maintain-ebay-limit', function () use ($limit) {

    if (!$this->confirm('Do You realy want to run this command!')) {
        $this->comment('Command run canceled.');
        return;
    }

    $next_page_url = '';
    $ebayItem = new EbayItems();
    $shopifyService = new ShopifyService();
    $ebay_quantity = $ebayItem->getTotalItemCount();
    $available_slot = $limit - $ebay_quantity;

    try {
        do {
            if ($available_slot <= 0) {
                break;
            }

            [$response, $res_headers] = $shopifyService->get_products(250, '', $next_page_url);
            $products = $response['products'] ?? [];

            if ($products) {
                foreach ($products as $product) {
                    if ($available_slot <= 0) {
                        break;
                    }
                    $price = (float) $product['variants'][0]['price'];
                    $qty = (int) $product['variants'][0]['inventory_quantity'];
                    $product_id = strval($product['id']);
                    if (!ItemSource::is_exists($product_id) && !FailedEbaySyncItem::is_exists($product_id, 5) && $price > 0 && $qty > 0) {
                        dispatch(new EbaySync(products: [$product]));
                        $available_slot -= 1;
                        $this->comment("JOB Dispatched Successfully Shopify Product ID:  $product_id Remaining slot $available_slot ");
                    } else {
                        $this->comment('Product already exist ID: ' . $product_id);
                    }
                }
            } else {
                $this->comment('[MAINTAIN EBAY LISTING] No products found in API response.');
            }

            $next_page_url = $shopifyService->extract_next_page_link($res_headers);
        } while ($next_page_url && $available_slot > 0);

        $this->comment("Schedule Call [MAINTAIN EBAY LISTING]: Successfuly dispatched new available slot jobs:" . $limit - $ebay_quantity);
    } catch (\Throwable $th) {
        $this->comment('Schedule Call ERROR [MAINTAIN EBAY LISTING]: ' . $th->getMessage());
    }
});

Artisan::command('store-all-products', function () {
    if ($this->confirm('Do you realy want to run this command!')) {
        dispatch(new ShopifyProductsJob());
    } else {
        $this->comment('Command run canceled!');
    }
});

Artisan::command('send-offers-to-interested-buyer', function () {
    try {
        $response = EbayService::find_eligible_items();
        if (!isset($response['eligibleItems'])) {
            Log::channel('cron_jobs')->error('[SEND OFFERS TO INTERESTED BUYERS] No eligible items found Response: ' . print_r($response, true));
            return;
        }

        $eligibleItems = $response['eligibleItems'];

        foreach ($eligibleItems as $object) {
            if (HelperService::get_interested_buyer_offered_item($object['listingId']) == 'initiated') {
                Log::channel('cron_jobs')->error("[SEND OFFERS TO INTERESTED BUYERS]  this item $object[listingId] already offered!");
                return;
            }

            $response = EbayService::send_offer_to_interested_buyers($object['listingId']);

            if (isset($response['errors'])) {
                $error = $response['errors'];
                if (isset($error['message']) && str_contains($error['message'], 'currently has a seller-initiated')) {
                    HelperService::set_interested_buyer_offered_item($object['listingId'], 'initiated');
                }
            } else {
                HelperService::set_interested_buyer_offered_item($object['listingId'], 'initiated');
            }
            Log::channel('cron_jobs')->info('[SEND OFFERS TO INTERESTED BUYERS] Send Offers response: ' . print_r($response, true));
        }
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->error('[SEND OFFERS TO INTERESTED BUYERS] Errro: ' . $th->getMessage());
    }
});

Artisan::command('apply-discount-to-aged-products', function () {

    if (!$this->confirm('Do you realy want to run this command?')) return;
    $discount_rules = [
        [
            'discount_percent' => 20,
            'old_days' => 14,
        ],
        [
            'discount_percent' => 30,
            'old_days' => 21,
        ],
    ];

    foreach ($discount_rules as $key => $rule) {
        $nextdays = null;
        if (isset($discount_rules[$key + 1])) {
            $nextdays = (int) $discount_rules[$key + 1]['old_days'] - (int) $rule['old_days'];
        }
        $old_items = EbayService::FindingService(200, $rule['old_days'], $nextdays);
        $old_items = json_decode(json_encode($old_items), true);

        if (!isset($old_items['searchResult']['item'])) {
            Log::channel('cron_jobs')->error('[APPLY DISCOUNT TO AGED PRODUCTS] Error: $old_items[searchResult][item] not set ' . print_r($old_items, true));
            return;
        }

        $ebay_item_IDs = [];
        foreach ($old_items['searchResult']['item'] as $item) {
            $saleItem = SaleItem::where('ebay_item_ids', 'LIKE', '%' . strval($item['itemId']) . '%')->first();

            if (!$saleItem) {
                $ebay_item_IDs[] = $item['itemId'];
                continue;
            }

            $get_markdowns = EbayService::get_markdown_sale($saleItem->promotion_url);

            if ($get_markdowns && $get_markdowns['promotionStatus'] == 'ENDED') {
                $ebay_item_IDs[] = $item['itemId'];
            }
        }

        Log::channel('cron_jobs')->info('[APPLY DISCOUNT TO AGED PRODUCTS] ebay_item_ids: ' . print_r($ebay_item_IDs, true));
        if (empty($ebay_item_IDs)) return Log::channel('cron_jobs')->info('[APPLY DISCOUNT TO AGED PRODUCTS] ebay_item_IDs is empty.');

        try {

            $res = EbayService::markdown_sale($ebay_item_IDs, $rule['discount_percent']);
            $endDate = $res['end_date'];
            $res_body = $res['body'];
            $res_headers = $res['headers'];

            if (!empty($res_body) && isset($res_body['errors'])) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Error response: ' . print_r($res_body, true));
            }
            if (!isset($res_headers['location']) && !isset($res_headers['Location'])) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Markdown sale RESPONSE HEADERS: ' . print_r($res_headers, true));
                return;
            }
            $location = isset($res_headers['location']) ?  reset($res_headers['location']) : reset($res_headers['Location']);
            if (empty($location)) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Markdown sale location not found! $res value: ' . print_r($res, true));
            }

            Log::channel('cron_jobs')->info(' [APPLY DISCOUNT TO AGED PRODUCTS] location URL ' . $location);

            $get_markdowns = EbayService::get_markdown_sale($location);
            Log::channel('cron_jobs')->info(' [APPLY DISCOUNT TO AGED PRODUCTS] get markdown response: ' . print_r($get_markdowns, true));

            $lisitng_ids = $ebay_item_IDs;
            if (isset($get_markdowns['selectedInventoryDiscounts'][0]['inventoryCriterion']['listingIds'])) {
                $lisitng_ids = $get_markdowns['selectedInventoryDiscounts'][0]['inventoryCriterion']['listingIds'];
            }

            SaleItem::create([
                'promotion_url' => $location,
                'ebay_item_ids' => json_encode($lisitng_ids),
                'discount_percentage' => $rule['discount_percent'],
                'sale_end_date' => $endDate,
                'old_days' => $rule['old_days']
            ]);

            Log::channel('cron_jobs')->info("[APPLY DISCOUNT TO AGED PRODUCTS] Discount Applied. discount_percent:$rule[discount_percent] old_days:$rule[old_days] ");
        } catch (\Throwable $th) {
            $err = $th->getMessage() . " in file " . $th->getFile() . " on line " . $th->getLine();
            Log::channel('cron_jobs')->info("[APPLY DISCOUNT TO AGED PRODUCTS] Error:  $err");
        }
    }
});


Artisan::command('delete-old-upload-new', function () {
    $ebayItem = new EbayItems();
    $shopifyService = new ShopifyService();
    $items_per_hour = 0;

    try {
        $items = HelperService::get_oldest_ebay_items();
        $total_old_items_count = count($items);
        // $this->comment(' total_old_items_count:  ' . $total_old_items_count);
        if ($total_old_items_count <= 0) return Log::channel('cron_jobs')->info("[DELETE & REUPLOADING NEW TOTAL/30]  NO 30 DAYS OLD ITEM FOUND!");
        $items_per_hour = $total_old_items_count > 14 ? intdiv($total_old_items_count, 14) : $total_old_items_count;
        $items = array_slice($items, 0, $items_per_hour); // output will be 5 items
        
        // $this->comment("old items: " . print_r($items, true));
        // $this->comment("old items count: " . count($items) . 'item_per_hour: ' . $items_per_hour);
        // return;

        foreach ($items as  $item) {
            $res = $ebayItem->EndItem($item['itemId']);
            DeletedEbayItem::store_ebay_deleted($item);

            Log::channel('cron_jobs')->info("[DELETE & REUPLOADING NEW TOTAL/30 = $total_old_items_count ITEMS] ItemsPerHour: $items_per_hour  ID:   $item[itemId] " . ' EndItem response: ' . print_r($res, true));
        }
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->error("Error [DELETE & REUPLOADING NEW TOTAL/30 ITEMS]: " . $th->getMessage());
    }

    // Start Importing new products
    $next_page_url = '';
    do {
        if ($items_per_hour <= 0) {
            break;
        }

        try {

            [$response, $res_headers] = $shopifyService->get_products(250, '', $next_page_url);
            $products = $response['products'] ?? [];

            if ($products) {
                foreach ($products as $product) {
                    if ($items_per_hour <= 0) {
                        break;
                    }
                    $product_id = strval($product['id']);
                    $price = (float) $product['variants'][0]['price'];
                    $qty = (int) $product['variants'][0]['inventory_quantity'];

                    if (!ItemSource::is_exists($product_id) && !FailedEbaySyncItem::is_exists($product_id, 5) && $qty > 0 && $price > 0) {
                        dispatch(new SyncShopifyToEbayItems(retry: [$product_id]));
                        $items_per_hour -= 1;
                    }
                }
            } else {
                Log::channel('cron_jobs')->error('[DELETE & REUPLOADING NEW TOTAL/30 ITEMS] No products found in Shopify API response.');
            }

            $next_page_url = $shopifyService->extract_next_page_link($res_headers);
        } catch (\Throwable $th) {
            Log::channel('cron_jobs')->error('Error Importing new Products [DELETE & REUPLOADING NEW TOTAL/30 ITEMS]: ' . $th->getMessage());
        }
    } while ($next_page_url);
});


// PENDING NEXT PAGE ITEMS.
// Schedule::call(function () {

//     try {
//         Log::channel('cron_jobs')->info('Schedule Call [PENDING REQUEST]: But not proceeeding for now  .');
//         return;

//         $pending_request = DB::table('pending_requests')->first();
//         if ($pending_request) {
//             $request_url = $pending_request->request_url;
//             $id = $pending_request->id;
//             DB::table('pending_requests')->where('id', $id)->delete();

//             dispatch(new SyncShopifyToEbayItems($request_url));

//             Log::channel('cron_jobs')->info('Schedule Call [PENDING REQUEST]: SUCCESSFULLY RUN PENDING REQUEST FOUND.');
//         } else {
//             Log::channel('cron_jobs')->info('Schedule Call [PENDING REQUEST]: NO PENDING REQUEST FOUND!');
//         }
//     } catch (\Throwable $th) {
//         Log::channel('cron_jobs')->error('Schedule Call [PENDING REQUEST]: ERROR: ' . $th->getMessage());
//     }
// })->everyFifteenMinutes();



// RETRY FAILED ITEMS.
Schedule::call(function () use ($limit) {
    $ebayItem = new EbayItems();
    $ebay_quantity = $ebayItem->getTotalItemCount();
    $available_slot = $limit - $ebay_quantity;
    if ($available_slot > 0) {
        $retry = [];
        try {
            $error_msg = HelperService::addItems_last_error_msg();
            if ($error_msg && str_contains(strtolower($error_msg), 'reached the number of items')) {
                Log::channel('[RETRY FAILED EBAY ITEMS] Active Errors: ' . $error_msg);
                return;
            }
            $failed_prod = DB::table('failed_ebay_sync_items')->where('tried', '<', 5)->get();
            if ($failed_prod->isEmpty()) {
                Log::channel('cron_jobs')->error('Schedule Call [RETRY FAILED ITEMS]: Not failed items found tried less than 5!');
                return;
            }
            foreach ($failed_prod as  $product) {
                if ((int) $product->tried < 5 && $available_slot > 0) {
                    $retry[] = $product->shopify_product_id;
                    dispatch(new SyncShopifyToEbayItems(null, $retry));
                    $available_slot -= 1;
                }
            }
            Log::channel('cron_jobs')->error('Schedule Call [RETRY FAILED ITEMS]: Successfuly dispatch job.');
        } catch (\Throwable $th) {
            Log::channel('cron_jobs')->error('Schedule Call ERROR [RETRY FAILED ITEMS]: ' . $th->getMessage());
        }
    } else {
        Log::channel('cron_jobs')->error('Schedule Call [RETRY FAILED ITEMS]: limit matched available slot: ' . $available_slot);
    }
})->everyOddHour();

// MAINTAIN EBAY LISTING LIMIT DAILY.
Schedule::call(function () use ($limit) {

    $next_page_url = '';
    $ebayItem = new EbayItems();
    $shopifyService = new ShopifyService();
    $ebay_quantity = $ebayItem->getTotalItemCount();
    $available_slot = $limit - $ebay_quantity;

    try {
        do {
            if ($available_slot <= 0) {
                break;
            }

            [$response, $res_headers] = $shopifyService->get_products(250, '', $next_page_url);
            $products = $response['products'] ?? [];

            if ($products) {
                foreach ($products as $product) {
                    if ($available_slot <= 0) {
                        break;
                    }
                    $price = (float) $product['variants'][0]['price'];
                    $qty = (int) $product['variants'][0]['inventory_quantity'];
                    $product_id = strval($product['id']);

                    if (!ItemSource::is_exists($product_id) && !FailedEbaySyncItem::is_exists($product_id, 5) && $price > 0 && $qty > 0) {
                        dispatch(new EbaySync(products: [$product]));
                        $available_slot -= 1;
                    }
                }
            } else {
                Log::channel('cron_jobs')->error('[MAINTAIN EBAY LISTING] No products found in API response.');
            }

            $next_page_url = $shopifyService->extract_next_page_link($res_headers);
        } while ($next_page_url && $available_slot > 0);

        Log::channel('cron_jobs')->error("Schedule Call [MAINTAIN EBAY LISTING]: Successfuly dispatched new available slot jobs:" . $limit - $ebay_quantity);
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->error('Schedule Call ERROR [MAINTAIN EBAY LISTING]: ' . $th->getMessage());
    }
})->cron('30 9 * * *')->timezone('Europe/London');



// DELETE & REUPLOADING NEW TOTAL/30 ITEMS - SPREAD THROUGHOUT THE DAY
Schedule::call(function () {
    $ebayItem = new EbayItems();
    $shopifyService = new ShopifyService();
    $items_per_hour = 0;

    try {
        $items = HelperService::get_oldest_ebay_items();
        $total_old_items_count = count($items);

        if ($total_old_items_count <= 0) return Log::channel('cron_jobs')->info("[DELETE & REUPLOADING NEW TOTAL/30]  NO 30 DAYS OLD ITEM FOUND!");

        $items_per_hour = $total_old_items_count > 14 ? intdiv($total_old_items_count, 14) : $total_old_items_count;
        $items = array_slice($items, 0, $items_per_hour);

        foreach ($items as  $item) {
            $res = $ebayItem->EndItem($item['itemId']);
            DeletedEbayItem::store_ebay_deleted($item);

            Log::channel('cron_jobs')->info("[DELETE & REUPLOADING NEW TOTAL/30 = $total_old_items_count ITEMS] ItemsPerHour: $items_per_hour  ID:   $item[itemId] " . ' EndItem response: ' . print_r($res, true));
        }
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->error("Error [DELETE & REUPLOADING NEW TOTAL/30 ITEMS]: " . $th->getMessage());
    }

    $next_page_url = '';
    do {
        if ($items_per_hour <= 0) {
            break;
        }

        try {

            [$response, $res_headers] = $shopifyService->get_products(250, '', $next_page_url);
            $products = $response['products'] ?? [];

            if ($products) {
                foreach ($products as $product) {
                    if ($items_per_hour <= 0) {
                        break;
                    }
                    $product_id = strval($product['id']);
                    $price = (float) $product['variants'][0]['price'];
                    $qty = (int) $product['variants'][0]['inventory_quantity'];

                    if (!ItemSource::is_exists($product_id) && !FailedEbaySyncItem::is_exists($product_id, 5) && $qty > 0 && $price > 0) {
                        dispatch(new SyncShopifyToEbayItems(retry: [$product_id]));
                        $items_per_hour -= 1;
                    }
                }
            } else {
                Log::channel('cron_jobs')->error('[DELETE & REUPLOADING NEW TOTAL/30 ITEMS] No products found in Shopify API response.');
            }

            $next_page_url = $shopifyService->extract_next_page_link($res_headers);
        } catch (\Throwable $th) {
            Log::channel('cron_jobs')->error('Error Importing new Products [DELETE & REUPLOADING NEW TOTAL/30 ITEMS]: ' . $th->getMessage());
        }
    } while ($next_page_url);
})->cron('10 8-22 * * *')->timezone('Europe/London');


// SEND OFFERS TO INTERESTED BUYERS
Schedule::call(function () {
    try {
        $response = EbayService::find_eligible_items();
        if (!isset($response['eligibleItems'])) {
            Log::channel('cron_jobs')->error('[SEND OFFERS TO INTERESTED BUYERS] No eligible items found in Response: ' . print_r($response, true));
            return;
        }

        $eligibleItems = $response['eligibleItems'];

        foreach ($eligibleItems as $object) {
            if (HelperService::get_interested_buyer_offered_item($object['listingId']) == 'initiated') {
                Log::channel('cron_jobs')->error("[SEND OFFERS TO INTERESTED BUYERS]  this item $object[listingId] already offered!");
                return;
            }

            $response = EbayService::send_offer_to_interested_buyers($object['listingId']);

            if (isset($response['errors'])) {
                $error = $response['errors'];
                if (isset($error['message']) && str_contains($error['message'], 'currently has a seller-initiated')) {
                    HelperService::set_interested_buyer_offered_item($object['listingId'], 'initiated');
                }
            } else {
                HelperService::set_interested_buyer_offered_item($object['listingId'], 'initiated');
            }
            Log::channel('cron_jobs')->info('[SEND OFFERS TO INTERESTED BUYERS] Send Offers response: ' . print_r($response, true));
        }
    } catch (\Throwable $th) {
        Log::channel('cron_jobs')->error('[SEND OFFERS TO INTERESTED BUYERS] Errro: ' . $th->getMessage());
    }
})->cron('20 11-22 * * *')->timezone('Europe/London');


// APPLY DISCOUNT TO AGED PRODUCTS
Schedule::call(function () {
    $discount_rules = [
        [
            'discount_percent' => 20,
            'old_days' => 14,
        ],
        [
            'discount_percent' => 30,
            'old_days' => 21,
        ],
    ];

    foreach ($discount_rules as $key => $rule) {
        $nextdays = null;
        if (isset($discount_rules[$key + 1])) {
            $nextdays = (int) $discount_rules[$key + 1]['old_days'] - (int) $rule['old_days'];
        }
        $old_items = EbayService::FindingService(200, $rule['old_days'], $nextdays);
        $old_items = json_decode(json_encode($old_items), true);

        if (!isset($old_items['searchResult']['item'])) {
            Log::channel('cron_jobs')->error('[APPLY DISCOUNT TO AGED PRODUCTS] Error: $old_items[searchResult][item] not set ' . print_r($old_items, true));
            return;
        }

        $ebay_item_IDs = [];
        foreach ($old_items['searchResult']['item'] as $item) {
            $saleItem = SaleItem::where('ebay_item_ids', 'LIKE', '%' . strval($item['itemId']) . '%')->first();

            if (!$saleItem) {
                $ebay_item_IDs[] = $item['itemId'];
                continue;
            }

            $get_markdowns = EbayService::get_markdown_sale($saleItem->promotion_url);

            if ($get_markdowns && $get_markdowns['promotionStatus'] == 'ENDED') {
                $ebay_item_IDs[] = $item['itemId'];
            }
        }

        Log::channel('cron_jobs')->info('[APPLY DISCOUNT TO AGED PRODUCTS] ebay_item_ids: ' . print_r($ebay_item_IDs, true));
        if (empty($ebay_item_IDs)) return Log::channel('cron_jobs')->info('[APPLY DISCOUNT TO AGED PRODUCTS] ebay_item_IDs is empty.');

        try {

            $res = EbayService::markdown_sale($ebay_item_IDs, $rule['discount_percent']);
            $endDate = $res['end_date'];
            $res_body = $res['body'];
            $res_headers = $res['headers'];

            if (!empty($res_body) && isset($res_body['errors'])) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Error response: ' . print_r($res_body, true));
            }
            if (!isset($res_headers['location']) && !isset($res_headers['Location'])) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Markdown sale RESPONSE HEADERS: ' . print_r($res_headers, true));
                return;
            }
            $location = isset($res_headers['location']) ?  reset($res_headers['location']) : reset($res_headers['Location']);
            if (empty($location)) {
                Log::channel('cron_jobs')->error(' [APPLY DISCOUNT TO AGED PRODUCTS] Markdown sale location not found! $res value: ' . print_r($res, true));
            }

            Log::channel('cron_jobs')->info(' [APPLY DISCOUNT TO AGED PRODUCTS] location URL ' . $location);

            $get_markdowns = EbayService::get_markdown_sale($location);
            Log::channel('cron_jobs')->info(' [APPLY DISCOUNT TO AGED PRODUCTS] get markdown response: ' . print_r($get_markdowns, true));

            $lisitng_ids = $ebay_item_IDs;
            if (isset($get_markdowns['selectedInventoryDiscounts'][0]['inventoryCriterion']['listingIds'])) {
                $lisitng_ids = $get_markdowns['selectedInventoryDiscounts'][0]['inventoryCriterion']['listingIds'];
            }

            SaleItem::create([
                'promotion_url' => $location,
                'ebay_item_ids' => json_encode($lisitng_ids),
                'discount_percentage' => $rule['discount_percent'],
                'sale_end_date' => $endDate,
                'old_days' => $rule['old_days']
            ]);

            Log::channel('cron_jobs')->info("[APPLY DISCOUNT TO AGED PRODUCTS] Discount Applied. discount_percent:$rule[discount_percent] old_days:$rule[old_days] ");
        } catch (\Throwable $th) {
            $err = $th->getMessage() . " in file " . $th->getFile() . " on line " . $th->getLine();
            Log::channel('cron_jobs')->info("[APPLY DISCOUNT TO AGED PRODUCTS] Error:  $err");
        }
    }
})->daily('8:15')->timezone('Europe/London');
