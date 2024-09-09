<?php

namespace App\Http\Controllers;

use App\Models\ItemSource;
use App\Services\ChatGPTService;
use App\Services\EbayItems;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class EbayToShopifyController extends Controller
{

    public function index(Request $request)
    {
        Log::channel('ebay_webhook')->info('Main Webhook called: ' . print_r($request->all(), true));
    }

    public function testing(Request $request)
    {
        Log::channel('ebay_webhook')->info('Testing Webhook: ' . print_r($request->all(), true));
    }

    public function ItemClosed(Request $request)
    {
    }
    public function ItemSold(Request $request)
    {

        if (!isset($request->payload->GetItemResponse->Item)) {
            Log::channel('ebay_webhook')->error('[ItemSold] Item not found in payload !');
            return;
        }
        $Item = $request->payload->GetItemResponse->Item;
        $item_id =  (string) $Item->ItemID;
        $quantity =  -intval($Item->Quantity);

        Log::channel('ebay_webhook')->error('[ItemSold] Method start ebay_item_qty: ' . $Item->Quantity . ' ebay_item_id: ' . $item_id);
        Log::channel('ebay_webhook')->error('[ItemSold] Request Payload: ' . print_r($request->payload, true));
        return;

        $item_source = ItemSource::where('ebay_item_id', $item_id)->first();
        if (!$item_source) {
            Log::channel('ebay_webhook')->error(' [ItemSold] Item does not found in DB!');
        }
        try {
            $shopifyService = new ShopifyService();
            $response = $shopifyService->update_inventory($item_source->inventory_item_id, $quantity);
            $item_source->last_stock = $Item->Quantity;
            $item_source->save();

            Log::channel('ebay_webhook')->info(' [ItemSold] Item updated successfully on shopify. Shopify item id: ' . $item_source->shopify_product_id . ' Response: ' . print_r($response, true));
        } catch (\Throwable $th) {
            Log::channel('ebay_webhook')->error(' [ItemSold] Error: ' . $th->getMessage());
        }
    }

    public function ItemOutOfStock(Request $request)
    {
        Log::channel('ebay_webhook')->info(' [ItemOutOfStock] Request payload ' . print_r($request->all(), true));
    }

    public function AuctionCheckoutComplete(Request $request)
    {
        // Log::channel('ebay_webhook')->info(' [AuctionCheckoutComplete] Request payload ' . print_r($request->all(), true));
        Log::channel('ebay_webhook')->info('[AuctionCheckoutComplete] Ebay webhook called.' );
        $payload = $request->payload;
        if(!isset($payload->GetItemTransactionsResponse)){
            $item_id = $payload->GetItemResponse->Item->ItemID;
            Log::channel('ebay_webhook')->info('[AuctionCheckoutComplete] (EndItem call) ebay item ID: ' . $item_id);
            $itemSource = ItemSource::where('ebay_item_id', strval($item_id))->first();
            if($itemSource) {
                $itemSource->last_stock = 0;
                $itemSource->save();
            } 
            return;
        }
        $order_id =  (string) $payload->GetItemTransactionsResponse->TransactionArray->Transaction->ContainingOrder->OrderID;
        $order_status =  (string) $payload->GetItemTransactionsResponse->TransactionArray->Transaction->ContainingOrder->OrderStatus;

        if (strtolower($order_status) != 'completed') {
            Log::channel('ebay_webhook')->info(' [AuctionCheckoutComplete] Order status not completed! Order status: ' . $order_status);
            return;
        }
        Log::channel('ebay_webhook')->info(' [AuctionCheckoutComplete] Order status completed! Order status: ' . $order_status . ' Order ID: '. $order_id);

        $ebayService = new EbayItems();
        $shopifyService = new ShopifyService();
        
        $order = $ebayService->get_order($order_id);
        $line_items = $order['orders'][0]['lineItems'];

        foreach ($line_items as $item) {
            // $sku = $item['sku'];
            $ebay_item_id = $item['legacyItemId'];
            $quantity     = -intval($item['quantity']);

            $itemSource = ItemSource::where('ebay_item_id', strval($ebay_item_id))->first();
            if (!$itemSource) {
                Log::channel('ebay_webhook')->error('Error: This ebay item does not exis in Database.  ID: ' . $ebay_item_id);
                continue;
            }

            try {
                $res = $shopifyService->update_inventory($itemSource->inventory_item_id, $quantity);
                Log::channel('ebay_webhook')->info('[AuctionCheckoutComplete] Inventory update response: ' . print_r($res, true));

            } catch (\Throwable $th) {
                Log::channel('ebay_webhook')->info(' [AuctionCheckoutComplete] Error: ' . $th->getMessage());
            }
        }
    }
}
