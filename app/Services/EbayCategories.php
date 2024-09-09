<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayCategories {
    protected $token;
    protected  $ebay_request_url;

    public function __construct()
    {
        $this->token = env('EBAY_ACCESS_TOKEN');
        $this->ebay_request_url = env('EBAY_ENDPOINT');
    }

    public function get(){
        $token = $this->token;
        $request_headers = [
            "X-EBAY-API-SITEID" => 3,
            "X-EBAY-API-COMPATIBILITY-LEVEL" => 967,
            "X-EBAY-API-CALL-NAME" => "GetCategories",
            'X-EBAY-API-IAF-TOKEN' => $token,
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
            <GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <DetailLevel>ReturnAll</DetailLevel>
                <ViewAllNodes>true</ViewAllNodes>
            </GetCategoriesRequest> 
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(50)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);

        } catch (\Throwable $th) {
            Log::channel('ebay_category')->error('Categories Get [Error]: '. $th->getMessage());
        }
         
        if ($response && str_contains($response->body(), 'Failure')) {
            Log::channel('ebay_category')->error('Ebay get categories Error: ' . print_r(simplexml_load_string($response->body()), true));
        } else {

            $responseXml = simplexml_load_string($response->body());
            dd($responseXml);
            if ($responseXml->Ack == 'Success' || $responseXml->Act == 'Warning') {
                return true;
            } else {
                Log::channel('ebay_category')->error(print_r($responseXml, true));
                return false;
            }
        }
        return false;
    }

    public function create($category_name){
        
        $token = $this->token;
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-CALL-NAME' => 'SetStoreCategories',
            'X-EBAY-API-IAF-TOKEN' => $token,
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
                <SetStoreCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                <Action>Add</Action>
                <StoreCategories>
                    <CustomCategory>
                    <Name>$category_name</Name>
                    <Order>1</Order>
                    </CustomCategory>
                </StoreCategories>
                </SetStoreCategoriesRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(30)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);

        } catch (\Throwable $th) {
            Log::channel('ebay_category')->error('Categories Create [Error]: '. $th->getMessage());
            return;
        }
        $responseXml = simplexml_load_string($response->body());
        // dd($responseXml);

        if ( $responseXml->Ack == 'Success' && $responseXml->Status == 'Complete') {
            $category_id = (string) $responseXml->CustomCategory->CustomCategory->CategoryID;
            $category = (string) $responseXml->CustomCategory->CustomCategory->Name;
            return [ 'category_id' => $category_id, 'category_name'=>$category];

        } else if( $responseXml->Status == 'Failed') {
            Log::channel('ebay_category')->error( 'Categrory creation [Error]: '.  print_r($responseXml, true));
        }
        return false;
    }

    public function get_store_categories(){

        $token = $this->token;
        $request_headers = [
            "X-EBAY-API-SITEID" => 3,
            "X-EBAY-API-COMPATIBILITY-LEVEL" => 967,
            "X-EBAY-API-CALL-NAME" => "GetStore",
            'X-EBAY-API-IAF-TOKEN' => $token,
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
            <GetStoreRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <LevelLimit>1</LevelLimit>
            </GetStoreRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(30)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);

        } catch (\Throwable $th) {
            Log::channel('ebay_category')->error('Store Categories Get [Error]: '. $th->getMessage());
            return;
        }
        $responseXml = simplexml_load_string($response->body());
        $cat = [];
        // dd($responseXml->Store->CustomCategories->CustomCategory);
        if ($responseXml->Ack == 'Failure') {
            Log::channel('ebay_category')->error('Ebay get Store categories [Failure]: ' . print_r($responseXml, true));

        } else {
            if ($responseXml->Ack == 'Success' || $responseXml->Ack == 'Warning') {
                foreach ($responseXml->Store->CustomCategories->CustomCategory as $key => $value) {
                    $cat[] = [
                        'category_id' => (string) $value->CategoryID, 
                        'name' => (string) $value->Name, 
                        'order' => (string) $value->Order, 
                    ];
                }
                return $cat;
            } else {
                Log::channel('ebay_category')->error(' get store categories [Error] ( Ack is not success ) ' .print_r($responseXml, true));
            }
        }
        return false;
    }

    public function GetSuggestedCategories($query){
        

        $query = htmlspecialchars($query);
        $token = $this->token;
        
        $request_headers = [
            "X-EBAY-API-SITEID" => 3,
            "X-EBAY-API-COMPATIBILITY-LEVEL" => 967,
            "X-EBAY-API-CALL-NAME" => "GetSuggestedCategories",
            'X-EBAY-API-IAF-TOKEN' => $token,
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
            <GetSuggestedCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                <Query>$query</Query>
            </GetSuggestedCategoriesRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(30)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);

        } catch (\Throwable $th) {
            Log::channel('ebay_category')->error('Suggested Categories Get [Error]: '. $th->getMessage());
            return;
        }

        $responseXml = simplexml_load_string($response->body());
        
        // dd($responseXml);
        if ($responseXml->Ack == 'Failure') {
            Log::channel('ebay_category')->error('Ebay get Suggestedcategories [Failure]: ' . print_r($responseXml, true));

        } else {
            if ($responseXml->Ack == 'Success' || $responseXml->Ack == 'Warning') {
               $category_id = (string) $responseXml->SuggestedCategoryArray->SuggestedCategory->Category->CategoryID;
               $name = (string) $responseXml->SuggestedCategoryArray->SuggestedCategory->Category->CategoryName;
               Log::channel('ebay_category')->error(' Get Suggested cateogries: ' .print_r($responseXml, true));
               return [
                'id'=>$category_id,
                'name'=>$name,
               ];
            } else {
                Log::channel('ebay_category')->error(' get store categories [Error] ( Ack is not success ) ' .print_r($responseXml, true));
            }
        }
        return false;
    }

    public function get_stored_category($product){

        $gender = HelperService::extractGender(strtolower($product['tags']));
        if(!$gender) return null;
        $product_type = strtolower($product['product_type']);
        Log::channel('sync_products')->info(' Product_type: ' . $product_type );
        $ebay_categories = config('ebay_categories');
        foreach ($ebay_categories as $type => $object) {
            if(strtolower($type) == $product_type){
                if(isset($object[$gender])){
                    // Log::channel('sync_products')->info('Gender: '. $object[$gender]);
                    return $object[$gender];
                }
            }
        }
        return null;
    }
}