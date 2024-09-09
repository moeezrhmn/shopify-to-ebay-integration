<?php

namespace App\Services;

use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayService
{


    protected $request_url;

    public function __construct()
    {
        $this->request_url = config('app.ebay.endpoint');
    }


    public static function FindingService($count = 10, $days = 7, $nextdays = null)
    {

        $startTo = Carbon::now('GMT')->subDays($days)->format('Y-m-d\TH:i:s\Z');
        $startTimeFromHTML = '';
        if ($nextdays) {
            $startFrom = Carbon::now('GMT')->subDays( (int) $days +  (int) $nextdays)->format('Y-m-d\TH:i:s\Z');
            $startTimeFromHTML = "<itemFilter>
            <name>StartTimeFrom</name>
            <value>$startFrom</value> 
            </itemFilter>";
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://svcs.ebay.com/services/search/FindingService/v1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="UTF-8"?>
                <findItemsAdvancedRequest xmlns="http://www.ebay.com/marketplace/search/v1/services">
                    <keywords></keywords>
                    
                    <itemFilter>
                        <name>Seller</name>
                        <value>vintageclubuk</value>
                    </itemFilter>

                    ' . $startTimeFromHTML . '

                    <itemFilter>
                        <name>StartTimeTo</name>
                        <value>' . $startTo . '</value>
                    </itemFilter>

                    <outputSelector>UnitPriceInfo</outputSelector>

                    <paginationInput>
                        <entriesPerPage>' . $count . '</entriesPerPage>
                    </paginationInput>
                </findItemsAdvancedRequest>
                ',
            CURLOPT_HTTPHEADER => array(
                'X-EBAY-SOA-SECURITY-APPNAME: jonathan-shopifys-PRD-9f5aec585-d5f763d5',
                'X-EBAY-SOA-OPERATION-NAME: findItemsAdvanced',
                'Content-Type: application/xml',
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = simplexml_load_string($response);
        return $response;
    }



    public static function getTotalItemCount()
    {
        $token = HelperService::get_oauth_token();
        $request_url = config('app.ebay.endpoint');
        if (!$request_url) {
            throw new Error('eBay request URL not found!');
        }

        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
        ];
        $requestxml = <<<XML
            <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <ActiveList>
                    <Pagination>
                        <EntriesPerPage>1</EntriesPerPage>
                        <PageNumber>1</PageNumber>
                    </Pagination>
                </ActiveList>
                <OutputSelector>ActiveList.PaginationResult.TotalNumberOfEntries</OutputSelector>
            </GetMyeBaySellingRequest>
        XML;

        $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $request_url, [
            'body' => $requestxml,
        ]);
        $response = simplexml_load_string($response->body());

        if (isset($response->ActiveList->PaginationResult->TotalNumberOfEntries)) {
            $activeCount = (int) $response->ActiveList->PaginationResult->TotalNumberOfEntries;
            return $activeCount;
        }
        Log::channel('ebay_service')->error('[getTotalItemCount] Error : ' . print_r($response, true));
        return false;
    }


    public static function find_eligible_items($limit = 30)
    {

        $token = HelperService::get_oauth_token();

        $response = Http::withHeaders([
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_GB',
            'Authorization' => "Bearer $token",
        ])->timeout(60)->send('GET', "https://api.ebay.com/sell/negotiation/v1/find_eligible_items?limit=$limit");

        return $response->json();
    }

    public static function send_offer_to_interested_buyers($ebay_item_id,  $discount = '15')
    {
        $token = HelperService::get_oauth_token();

        $jsonPayload = [
            "message" => "Great News! Get my item at a discounted price.",
            "offeredItems" => [
                [
                    "quantity" => "1",
                    "listingId" => (string) $ebay_item_id,
                    "discountPercentage" => $discount
                ]
            ],

            "allowCounterOffer" => "false",
            "offerDuration" => [
                "unit" => "DAY",
                "value" => "2"
            ]
        ];
        // return $jsonPayload;
        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_GB',
        ])->timeout(60)->send('POST', 'https://api.ebay.com/sell/negotiation/v1/send_offer_to_interested_buyers', [
            'body' => json_encode($jsonPayload)
        ]);

        return $response->json();
    }

    public static function markdown_sale($ebay_item_IDs = [], $discount_percentage = 10){

        $token = HelperService::get_oauth_token();
        $startDate = Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $endDate = Carbon::now('UTC')->subDays(-6)->format('Y-m-d\TH:i:s.v\Z');

        if (empty($ebay_item_IDs)) {
            throw new Exception("Variable ebay_item_IDs is empty array!", 1);
        }
        $payload = [
                "name" => "$discount_percentage% Off",
                "description" => "Get $discount_percentage% off for these items.",
                "startDate" => "$startDate",
                "endDate" => "$endDate",
                "marketplaceId" => "EBAY_GB",
                "promotionStatus" => "SCHEDULED",
                "promotionImageUrl" => "https://i.ebayimg.com/images/g/aT4AAOSw9tpl8eX6/s-l140.webp", 
                "selectedInventoryDiscounts" => [
                    [
                        "inventoryCriterion" => [
                            "inventoryCriterionType" => "INVENTORY_BY_VALUE",
                            "listingIds" => $ebay_item_IDs
                        ],
                        "discountBenefit" => [
                            "percentageOffItem" => $discount_percentage
                        ]
                    ]
                ]
        ];
        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",             
            'Content-Type' => 'application/json'
        ])->timeout(60)->send('POST', 'https://api.ebay.com/sell/marketing/v1/item_price_markdown', [
            'body' => json_encode($payload) 
        ]);

        return [ 
            'body' =>  $response->json(),
            'headers' =>  $response->headers(),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public static function get_markdown_sale($promotion_url){

        $token = HelperService::get_oauth_token();

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",             
            'Content-Type' => 'application/json'
        ])->timeout(60)->send('GET', $promotion_url);

        return $response->json();
    }

    public static function update_markdown_sale($promotion_url, $payload){

        $token = HelperService::get_oauth_token();

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",             
            'Content-Type' => 'application/json'
        ])->timeout(60)->send('PUT', $promotion_url, [
            'body' => $payload
        ]);
        return $response->json();
    }
}
