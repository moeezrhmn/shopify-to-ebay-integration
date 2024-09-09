<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyToEbayItems;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShopifyController extends Controller
{
    public function index()
    {
        return view('shopify.index');
    }
    public function get_store_products(Request $request)
    {

        $validated = $request->validate([
            'created_at_min' => 'required',
            'created_at_max' => 'required',
        ]);
        $cacheKey = "shopify_products_{$validated['created_at_min']}_to_{$validated['created_at_max']}";

        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            $products = $cachedData['products'];
            $total_count = $cachedData['total_count'];
        } else {
            try {
                $params = "&created_at_min=$validated[created_at_min]&created_at_max=$validated[created_at_max]";
                $shopifyService = new ShopifyService();
                [$products, $res_headers] = $shopifyService->get_products(limit: 250, params: $params);
                $total_count =  $shopifyService->get_count($params);

                Cache::put($cacheKey, ['products' => $products, 'total_count' => $total_count], now()->addMinutes(30));
            } catch (\Throwable $th) {
                return view('shopify.index')->with('message', 'Error:' . $th->getMessage());
            }
        }

        return view('shopify.index', compact('products', 'total_count'));
    }

    public function sync_to_ebay(Request $request)
    {

        $validated =  $request->validate([
            'ids' => 'required'
        ]);
        dd($validated);
        try {

            foreach ($validated['ids'] as $id) {
                dispatch(new SyncShopifyToEbayItems(retry: [$id]));
            }

            return response()->json([
                'status' => true,
                'message' => 'Successfully requested to sync items on eBay.'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $th->getMessage()
            ]);
        }
    }
}
