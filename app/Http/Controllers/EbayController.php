<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyToEbayItems;
use App\Models\ItemSource;
use App\Services\EbayItems;
use App\Services\ShopifyService;
use Illuminate\Http\Request;

class EbayController extends Controller
{
    protected $shopifyService;
    protected $eBayService;

    public function __construct()
    {
        $this->shopifyService = new ShopifyService();
        $this->eBayService = new EbayItems();
    }

    public function index()
    {

        return view('ebay.index');
    }

    public function complete_item_update(Request $request)
    {
        $item_id = $request->item_id;
        $itemSource = ItemSource::where('ebay_item_id', $item_id)->first();

        if (!$itemSource) {
            return redirect()->back()->with('message', 'This Item id does not exist!');
        }

        $shopify_product_id = $itemSource->shopify_product_id;
        $shopify_product = $this->shopifyService->get_single_product($shopify_product_id);
        $shopify_product = $shopify_product['product'];
        if (empty($shopify_product)) {
            return redirect()->back()->with('message', 'Shopify item not found!');
        }

        $res = $this->eBayService->update($shopify_product, $item_id);
        if (!$res) {
            return redirect()->back()->with('message', 'Could not update item! An internal error occured.');
        }

        return redirect()->back()->with('message', 'Ebay item updated successfully.');
    }



    public function title_update(Request $request)
    {
        $item_id = $request->item_id;
        $itemSource = ItemSource::where('ebay_item_id', $item_id)->first();

        if (!$itemSource) {
            return redirect()->back()->with('message', 'This Item id does not exist!');
        }

        $shopify_product_id = $itemSource->shopify_product_id;
        $shopify_product = $this->shopifyService->get_single_product($shopify_product_id);
        $shopify_product = $shopify_product['product'];
        if (empty($shopify_product)) {
            return redirect()->back()->with('message', 'Shopify item not found!');
        }

        $res = $this->eBayService->update_item_title($shopify_product, $item_id);
        if (!$res) {
            return redirect()->back()->with('message', 'Could not update item title! An internal error occured.');
        }

        return redirect()->back()->with('message', 'Ebay item title updated successfully.');
    }



    public function item_specifics_update(Request $request)
    {
        $item_id = $request->item_id;
        $itemSource = ItemSource::where('ebay_item_id', $item_id)->first();

        if (!$itemSource) {
            return redirect()->back()->with('message', 'This Item id does not exist!');
        }

        $shopify_product_id = $itemSource->shopify_product_id;
        $shopify_product = $this->shopifyService->get_single_product($shopify_product_id);
        $shopify_product = $shopify_product['product'];

        if (empty($shopify_product)) {
            return redirect()->back()->with('message', 'Shopify item not found!');
        }

        $res = $this->eBayService->update_item_specifics($shopify_product, $item_id);
        if (!$res) {
            return redirect()->back()->with('message', 'Could not update item specifics! An internal error occured.');
        }
        return redirect()->back()->with('message', 'Ebay item specifics updated successfully.');
    }
}
