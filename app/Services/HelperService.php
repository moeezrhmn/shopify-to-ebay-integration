<?php



namespace App\Services;

use App\Models\ItemSource;
use Carbon\Carbon;
use Error;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\error;

class HelperService
{



    public static function remove_ebay_item_template($ebay_item_ids)
    {

        $url = env('TEMPLATE_BUILDER_URL', 'https://ebay-template-builder.vintageclubmysteryboxsoftware.com');

        foreach ($ebay_item_ids as  $item_id) {

            $request_url = $url . '/api/ebay/remove-template/' . $item_id;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer 1|RB78qoDgH4Vq8s9LqFGsqeo6Ninb79eLWNJsJSBu907ac95c',
                'Accept' => 'application/json'
            ])->post($request_url);

            $responseArray = $response->json();
            // Log::channel('template_builder')->info('Response: ' . print_r($responseArray, true));

            if (isset($responseArray['status']) && $responseArray['status'] == true) {
                Log::channel('template_builder')->info('SUCCESS: ' . $responseArray['message']);
                $itemSource = ItemSource::where('ebay_item_id', strval($item_id))->first();
                if ($itemSource) {
                    $itemSource->template_applied = 0;
                    $itemSource->save();
                }
                return $responseArray['message'];
            } else {
                // TemplateJob::dispatch($this->ebay_item_ids);
                Log::channel('template_builder')->error('ERROR: Message -> ' . $responseArray['message'] . ' in file ' . $responseArray['file'] . ' on line ' . $responseArray['line']);
                return $responseArray['message'];
            }
        }
    }

    public static function extractGender($tags)
    {
        $tags = strtolower($tags);
        $gender = null;
        if (str_contains($tags, ' men')) {
            $gender = 'men';
        } elseif (str_contains($tags, 'women')) {
            $gender = 'women';
        }
        return $gender;
    }

    public static function ifElseCheck($param)
    {
        return empty($param) ? '' : $param;
    }


    public static function get_oauth_token($auth_n_auth = false)
    {
        if ($auth_n_auth) {
            return config('app.ebay.auth_n_auth');
        }

        if (!empty(Cache::get('ebay_oauth_token'))) {
            return Cache::get('ebay_oauth_token');
        }

        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.ebay.com/identity/v1/oauth2/token',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => 'grant_type=refresh_token&refresh_token=v%5E1.1%23i%5E1%23p%5E3%23r%5E1%23f%5E0%23I%5E3%23t%5EUl4xMF8xOjczQzg1MjUxOTFBN0JGODVGMzNCNDU4MDA3NkM5REEwXzBfMSNFXjI2MA%3D%3D&redirect_uri=jonathan_green-jonathan-shopif-mjyomyx',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic am9uYXRoYW4tc2hvcGlmeXMtUFJELTlmNWFlYzU4NS1kNWY3NjNkNTpQUkQtZjVhZWM1ODVkNjI5LWNiMmYtNDkzYS04ZDNjLTQ3ODg=',
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cookie: dp1=bu1p/QEBfX0BAX19AQA**6a6a3347^; ebay=%5Esbf%3D%23%5E'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response, true);

            if (isset($response['access_token'])) {
                $token = $response['access_token'];
                Cache::put('ebay_oauth_token', $token, now()->addMinutes(115));

                return Cache::get('ebay_oauth_token');
            } else {
                Log::channel('cron_jobs')->error('Failed to refresh eBay OAuth token: ' . $response);
            }
        } catch (\Throwable $th) {
            Log::channel('cron_jobs')->error('Failed to refresh eBay OAuth token: ' . $th->getMessage());
        }
    }


    public static function extract_img_url($product)
    {
        return isset($product['image']['src']) ? $product['image']['src'] : (isset($product['images'][0]['src']) ? $product['images'][0]['src'] : '');
    }

    public static function is_item_in_ebay($shopify_product_id)
    {
        $itemSource = ItemSource::where('shopify_product_id', strval($shopify_product_id))->first();
        if ($itemSource) {
            return $itemSource->ebay_item_id;
        }
        return null;
    }


    public static function get_oldest_ebay_items($denominator = 30, $days = 30)
    {   
        $items_count = 100;
        if(!$denominator <= 0){
            $total_count = EbayService::getTotalItemCount();
            if (!$total_count) {
                throw new Error('Error [get_oldest_ebay_items]: $total_count empty ' . $total_count);
            }
            $items_count = round($total_count / $denominator);
        }

        $response = EbayService::FindingService($items_count, $days);
        if ($response->Ack == 'Failure' || !isset($response->searchResult)) {
            throw new Error(print_r($response, true));
        }

        $items = json_encode($response->searchResult);
        $items = json_decode($items, true);
        if(!isset($items['item'])) return [];
        return  $items['item'];
    }

    public static function parse_failed_prod_errors($errors)
    {
        $errors = json_decode($errors, true);
        switch (true) {
            case isset($errors['Ack']) && isset($errors['AddItemResponseContainer']):
                $output = '';
                foreach ($errors['AddItemResponseContainer']['Errors'] as $err) {
                    // dd($err['SeverityCode']);
                    if (is_array($err) && isset($err['SeverityCode']) && $err['SeverityCode']  == 'Error') {
                        $output .=  $err['ShortMessage'] . "\n";
                    }
                }
                return $output;
            case isset($errors['Errors']):
                if (isset($errors['Errors']['LongMessage'])) {
                    return $errors['Errors']['LongMessage'];
                } else {
                    return print_r($errors['Errors'], true);
                }
            case isset($errors['error']):
                return $errors['error'];
            default:
                // return '<pre>' . print_r($errors, true) .  '</pre>';
                return 'ERROR NOT FOUND!';
        }
    }

    public static function addItems_last_error_msg($msg = '')
    {
        if ($msg) {
            Cache::put('additems_err_msg', $msg, 1800);
        } else {
            return Cache::get('additems_err_msg');
        }
    }

    public static function get_interested_buyer_offered_item($ebay_item_id){
        return Cache::get("offered_item_$ebay_item_id");
    }
    public static function set_interested_buyer_offered_item($ebay_item_id, $value){
        Cache::put("offered_item_$ebay_item_id", $value, 10368000);
    }
}
