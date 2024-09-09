<?php

namespace App\Services;

use App\Models\ItemSpecific;
use Error;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayItems
{
    protected $token;
    protected $auth_n_auth_token;
    protected $ebay_request_url;
    protected $category_client;
    protected $new_title;
    protected $new_description;
    protected $chatGPTService;
    protected $current_category_id;

    public function __construct()
    {
        $this->category_client = new EbayCategories();
        $this->chatGPTService = new ChatGPTService();
        $this->auth_n_auth_token = env('EBAY_ACCESS_TOKEN');
        $this->ebay_request_url = env('EBAY_ENDPOINT');
    }

    public  function insert(array $products = [])
    {
        if(str_contains(strtolower(HelperService::addItems_last_error_msg()), 'reached the number of items')){
            Log::channel('sync_products')->error(' Last error message is still active. ' . HelperService::addItems_last_error_msg());
            return false;
        }
        $token = HelperService::get_oauth_token();

        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'AddItems',
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
            <AddItemsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
        XML;

        foreach ($products as $index => $product) {

            if (strlen($product['body_html']) > 4000) {
                Log::channel('sync_products')->error('ERROR: Body HTML cannot more than 4000 characters.');
                continue;
            }

            $variant = $product['variants'][0];

            $gptContent = $this->chatGPTReviseContent($product);
            $this->new_title = $gptContent['title'];
            $this->new_description = $gptContent['description'];

            if (empty($gptContent['item_specifics'])) {
                $gptContent['item_specifics'] = $this->chatGPTService->item_specifics($product);
            }

            $queryForCat = $this->queryForCategory($product);
            // Log::channel('sync_products')->info('Category sugggestion Query: ' . $queryForCat);
            $category_id = $this->category_client->get_stored_category($product);
            if (!$category_id) {
                $category =  $this->category_client->GetSuggestedCategories($queryForCat);
                $category_id = $category['id'];
                // Log::channel('sync_products')->info('Category id: ' . $category_id . ' Name: ' . $category['name']);
            }
            $this->current_category_id = $category_id;
            // return;

            $item_specifics = $this->get_item_specifics($gptContent['item_specifics'], $product);
            // Log::channel('sync_products')->info('Item specific: '. print_r($item_specifics,true));

            if (!$category_id) {
                Log::channel('sync_products')->error('Category not found for this item!! Shopify ID: ' . $product['id']);
            }

            $img_urls = [];
            if (!empty($this->extract_img_url($product))) {
                $img_urls = $this->extract_img_url($product);
            }

            $quantity = $variant['inventory_quantity'];
            $sku = (string) $variant['sku'];
            $price = $this->roundPrice($variant['price']);

            $miniBestOffer = (float) $price / 100 * 80;

            $tabs_html = $this->generate_tabs_html($gptContent['advanced_description']);
            $tabs_html = $this->escapeXml($tabs_html);
            $description = $this->escapeXml($this->new_description);
            $description = $description . $tabs_html;

            Log::channel('sync_products')->info('OLD Title: { ' . $product['title'] . ' } ---  Shopify Product ID: ' . $product['id']);
            Log::channel('sync_products')->info('NEW Title: ' . $gptContent['title']);
            // Log::channel('sync_products')->info('Body HTML After chatGPT: '. $description);

            $title = $this->upperCaseFirstWord($this->new_title);
            $title = $this->escapeXml($title);
            $requestxml .= <<<XML
                <AddItemRequestContainer>
                    <MessageID>$index</MessageID>
                    <Item>
                        <Title>$title</Title>
                        <Description>$description</Description>
                        <PrimaryCategory>
                            <CategoryID>$category_id</CategoryID>
                        </PrimaryCategory>
                        <BestOfferDetails> 
                            <BestOfferEnabled>true</BestOfferEnabled>
                        </BestOfferDetails>
                        <ListingDetails>
                            <BestOfferAutoAcceptPrice currencyID="GBP">$miniBestOffer</BestOfferAutoAcceptPrice>
                            <MinimumBestOfferPrice currencyID="GBP">$miniBestOffer</MinimumBestOfferPrice>
                        </ListingDetails>
                        <CategoryMappingAllowed>true</CategoryMappingAllowed>
                        <ConditionID>3000</ConditionID>
                        <ConditionDescription>100% GENUINE - $gptContent[item_used_condition] - FAST POSTAGE</ConditionDescription>
                        <Site>UK</Site>
                        <Quantity>$quantity</Quantity>
                        <SKU>$sku</SKU>
                        <StartPrice currencyID="GBP">$price</StartPrice>
                        <AutoPay>true</AutoPay>
                        <ListingDuration>GTC</ListingDuration>
                        <ListingType>FixedPriceItem</ListingType>
                        <DispatchTimeMax>0</DispatchTimeMax>
                        <ShippingDetails>
                            <GlobalShipping>true</GlobalShipping>
                            <ShippingType>Flat</ShippingType>
                            <ShippingServiceOptions>
                                <ShippingServicePriority>1</ShippingServicePriority>
                                <ShippingService>UK_RoyalMailTracked</ShippingService>
                                <ShippingServiceCost>0</ShippingServiceCost>
                                <ShippingServiceAdditionalCost>0</ShippingServiceAdditionalCost>
                            </ShippingServiceOptions>
                            <ShippingServiceOptions>
                                <ShippingServicePriority>1</ShippingServicePriority>
                                <ShippingService>UK_RoyalMailNextDay</ShippingService>
                                <ShippingServiceCost>3.00</ShippingServiceCost>
                                <ShippingServiceAdditionalCost>0</ShippingServiceAdditionalCost>
                            </ShippingServiceOptions>
                            <ShipToLocations>Worldwide</ShipToLocations>
                            <InternationalShippingServiceOption>
                                <ShippingService>UK_RoyalMailAirmailInternational</ShippingService>
                                <ShippingServiceAdditionalCost currencyID="GBP">0</ShippingServiceAdditionalCost>
                                <ShippingServiceCost currencyID="GBP">12.00</ShippingServiceCost>
                                <ShippingServicePriority>1</ShippingServicePriority>
                                <ShipToLocation>Worldwide</ShipToLocation>
                            </InternationalShippingServiceOption>
                        </ShippingDetails>
                        <ReturnPolicy>
                            <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>
                            <ReturnsWithinOption>Days_60</ReturnsWithinOption>
                            <ShippingCostPaidByOption>Buyer</ShippingCostPaidByOption>
                        </ReturnPolicy>
                        <Country>GB</Country>
                        <Currency>GBP</Currency>
                        <Location>London</Location>
            XML;

            $requestxml .= <<<XML
                <ItemSpecifics>
            XML;

            foreach ($item_specifics as $key => $value) {
                $requestxml  .= <<<XML
                    <NameValueList> 
                        <Name> $key </Name>
                        <Value>$value</Value> 
                    </NameValueList>
                XML;
            }
            $requestxml  .= <<<XML
                </ItemSpecifics>
                        <PictureDetails>
                            <GalleryType>Gallery</GalleryType>
            XML;
            foreach ($img_urls as $imgURL) {
                $requestxml .= <<<XML
                    <PictureURL>$imgURL</PictureURL>
                XML;
            }
            $requestxml .= <<<XML
                        </PictureDetails>
                    </Item>
                 </AddItemRequestContainer>
            XML;
        }
        $requestxml .= <<<XML
            </AddItemsRequest>
        XML;
        // Log::channel('sync_products')->info('Request XML: '. $requestxml );
        // return;
        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
            // dd($response->body());

        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('[Sync Item Request Error]: ' . $th->getMessage());
            return;
        }

        // Log::info('XML Response => '. $response->body());
        // dd($responseXml);
        $responseXml = simplexml_load_string($response->body());

        if ($responseXml->Ack == 'Failure') {
            // Log::channel('sync_products')->error('Ebay additems Error: ' . print_r($responseXml->AddItemResponseContainer, true));
            $this->store_failed_sync($product['id'], $responseXml, $product['title'], $product['body_html'], $gptContent['title'], $description . "\n\n\n item_specifics:" . print_r($item_specifics, true));
            $error_message = HelperService::parse_failed_prod_errors(json_encode($responseXml));
            HelperService::addItems_last_error_msg($error_message);
        } else {
            // dd($responseXml);
            Log::channel('sync_products')->info('Response after load string => ', [$responseXml]);

            if ($responseXml->Ack == 'Success' || $responseXml->Ack == 'Warning') {
                Log::channel('sync_products')->info('Item batch listed.');

                $itemIDs = [];
                foreach ($responseXml->AddItemResponseContainer as $container) {
                    $itemIDs[] = (string) $container->ItemID;
                }

                return $itemIDs;
            } else {
                Log::channel('sync_products')->error(print_r($responseXml, true));
                return false;
            }
        }
        return false;
    }

    public function update($product, $item_id)
    {
        $token = HelperService::get_oauth_token();

        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
        ];

        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
        XML;

        if (strlen($product['body_html']) > 4000) {
            Log::channel('sync_products')->error('ERROR: Body HTML cannot more than 4000 characters.');
        }

        $variant = $product['variants'][0];

        $gptContent = $this->chatGPTReviseContent($product);
        $this->new_title = $gptContent['title'];
        $this->new_description = $gptContent['description'];

        if (empty($gptContent['item_specifics'])) {
            $gptContent['item_specifics'] = $this->chatGPTService->item_specifics($product);
        }

        $category_id = $this->category_client->get_stored_category($product);
        Log::channel('sync_products')->info('Stored Category: ' . $category_id);
        // return;
        if (!$category_id) {
            $queryForCat = $this->queryForCategory($product);
            Log::channel('sync_products')->info('Category Query: ' . $queryForCat);
            $category =  $this->category_client->GetSuggestedCategories($queryForCat);
            $category_id = $category['id'];
            Log::channel('sync_products')->info('Category id: ' . $category_id . ' Name: ' . $category['name']);
        }
        $this->current_category_id = $category_id;
        // Log::channel('sync_products')->info('Category id: ' . $category_id);
        // return;

        $item_specifics = $this->get_item_specifics($gptContent['item_specifics'], $product);
        // Log::channel('sync_products')->info('Item specific: '. print_r($item_specifics,true));

        if (!$category_id) {
            Log::channel('sync_products')->error('Category not found for this item!! Shopify ID: ' . $product['id']);
        }

        $img_urls = [];
        if (!empty($this->extract_img_url($product))) {
            $img_urls = $this->extract_img_url($product);
        }

        $quantity = $variant['inventory_quantity'];
        $sku = (string) $variant['sku'];
        $price = $this->roundPrice($variant['price']);

        $miniBestOffer = (float) $price / 100 * 80;

        $tabs_html = $this->generate_tabs_html($gptContent['advanced_description']);
        $tabs_html = $this->escapeXml($tabs_html);
        $description = $this->escapeXml($this->new_description);
        $description = $description . $tabs_html;

        Log::channel('sync_products')->info('OLD Title: { ' . $product['title'] . ' } ---  Shopify Product ID: ' . $product['id']);
        Log::channel('sync_products')->info('NEW Title: ' . $gptContent['title']);
        // Log::channel('sync_products')->info('Body HTML After chatGPT: '. $description);

        $title = $this->upperCaseFirstWord($this->new_title);
        $title = $this->escapeXml($title);
        // <Description>$description</Description>
        $requestxml .= <<<XML
                    <Item>
                        <ItemID>$item_id</ItemID>
                        <Title>$title</Title>
                        <PrimaryCategory>
                            <CategoryID>$category_id</CategoryID>
                        </PrimaryCategory>
                        <BestOfferDetails> 
                            <BestOfferEnabled>true</BestOfferEnabled>
                        </BestOfferDetails>
                        <ListingDetails>
                            <BestOfferAutoAcceptPrice currencyID="GBP">$miniBestOffer</BestOfferAutoAcceptPrice>
                            <MinimumBestOfferPrice currencyID="GBP">$miniBestOffer</MinimumBestOfferPrice>
                        </ListingDetails>
                        <CategoryMappingAllowed>true</CategoryMappingAllowed>
                        <ConditionID>3000</ConditionID>
                        <ConditionDescription>100% GENUINE - $gptContent[item_used_condition] - FAST POSTAGE</ConditionDescription>
                        <Site>UK</Site>
                        <Quantity>$quantity</Quantity>
                        <SKU>$sku</SKU>
                        <AutoPay>true</AutoPay>
                        <StartPrice>$price</StartPrice>
            XML;

        $requestxml .= <<<XML
                <ItemSpecifics>
            XML;

        foreach ($item_specifics as $key => $value) {
            $requestxml  .= <<<XML
                    <NameValueList> 
                        <Name> $key </Name>
                        <Value>$value</Value> 
                    </NameValueList>
                XML;
        }
        $requestxml  .= <<<XML
                </ItemSpecifics>
                        <PictureDetails>
                            <GalleryType>Gallery</GalleryType>
            XML;
        foreach ($img_urls as $imgURL) {
            $requestxml .= <<<XML
                    <PictureURL>$imgURL</PictureURL>
                XML;
        }
        $requestxml .= <<<XML
                        </PictureDetails>
                    </Item>
            XML;
        $requestxml .= <<<XML
            </ReviseItemRequest>
        XML;

        // Log::channel('sync_products')->info('Request XML: '. $requestxml );
        // return;
        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
            // dd($response->body());

        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('[Sync Item Request Error]: ' . $th->getMessage());
            return;
        }

        // Log::info('XML Response => '. $response->body());
        $responseXml = simplexml_load_string($response->body());
        // dd($responseXml);

        if ($responseXml->Ack == 'Failure') {
            // Log::channel('sync_products')->error('Ebay additems Error: ' . print_r($responseXml->AddItemResponseContainer, true));
            $this->store_failed_sync($product['id'], $responseXml, $product['title'], $product['body_html'], $gptContent['title'], $description . "\n\n\n item_specifics:" . print_r($item_specifics, true));
        } else {
            // dd($responseXml);
            Log::channel('sync_products')->info('Response after load string => ', [$responseXml]);

            if ($responseXml->Ack == 'Success' || $responseXml->Ack == 'Warning') {
                Log::channel('sync_products')->info('Item updated success.');
                return true;
            } else {
                Log::channel('sync_products')->error(print_r($responseXml, true));
                return false;
            }
        }
        return false;
    }

    protected function escapeXml($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    /**
     * Extract product image url
     */
    protected function extract_img_url($product)
    {
        $image_urls = [];
        foreach ($product['images'] as $img) {
            $image_urls[] =  !empty($img['src']) ? $img['src'] : '';
        }
        return $image_urls;
    }

    protected function chatGPTReviseContent($product)
    {
        $chatgpt = $this->chatGPTService;;
        // Log::channel('sync_products')->info('Chat GPT before title: '. $title);
        // Log::channel('sync_products')->info('Chat GPT before description: '. $description);
        $output = [];
        $output['title'] = $chatgpt->title($product);
        if (strlen($output['title']) > 80) {
            $output['title'] = $chatgpt->title($product);
        }
        $output['description'] = $chatgpt->description($product);
        $output['item_specifics'] = $chatgpt->item_specifics($product);
        $output['advanced_description'] = $chatgpt->advanced_description($product);
        $output['item_used_condition'] = $chatgpt->item_used_condition($product);

        $output['description'] = str_replace('Item is in good used condition', 'Item has ' . $output['item_used_condition'], $output['description']);
        // Log::channel('sync_products')->info('GPT CONTENT: '. print_r($output, true));
        return $output;
    }

    protected function get_category_id($product_type)
    {
        $ebay_cat_client =  new EbayCategories();
        $store_cats = $ebay_cat_client->get_store_categories();
        $product_type_lower = strtolower($product_type);

        foreach ($store_cats as $cat) {
            $s_cat_name = strtolower($cat['name']);
            if ($product_type_lower == $s_cat_name) {
                return strval($cat['category_id']);
            }
        }
        $output = $ebay_cat_client->create($product_type);
        return strval($output['category_id']);
    }

    public function generate_tabs_html($data)
    {
        // Keys - Condition Guide, Sizing Guide, Delivery Info, Returns Info
        // $size= $item_specifics['Size'];
        // $armpitToArmpit= $item_specifics['Armpit To Armpit'];
        // $ArmpitToCuff= $item_specifics['Armpit To Cuff'];
        // $CollarToHem= $item_specifics['Collar To Hem'];

        $Condition_Guide = $data['Condition Guide'];
        $Sizing_Guide = $data['Sizing Guide'];
        $Delivery_Info = $data['Delivery Info'];
        $Returns_Info = $data['Returns Info'];
        $html = <<<HTML
            <div id="max_wrap_item_policy_upper" >
                <style>
                    #max_wrap_item_policy div#max_wrap_item_policy_inner {
                    padding: 25px;
                    border: 1px solid #000;
                    overflow: hidden;
                    overflow-y: auto;
                    font-family: inherit;
                    color: black;
                    display: flex;
                    justify-content: space-evenly;
                    }
                    #max_wrap_item_policy div#max_wrap_item_policy_inner div {
                    display: flex;
                    align-items: center;
                    }
                    #max_wrap_item_policy div#max_wrap_item_policy_inner div span {
                    white-space: nowrap;
                    font-weight: 700;
                    }
                    @media screen and (max-width: 768px) {
                        #max_wrap_item_policy div#max_wrap_item_policy_inner {
                            justify-content: center;
                            flex-wrap: wrap;
                        }
                        #max_wrap_item_policy div#max_wrap_item_policy_inner div {
                            margin: 20px;
                            display:flex;
                            flex-direction:column;
                            align-items:center;
                            justify-content:center;
                        }
                    }
                </style>
                <div id="max_wrap_item_policy" style="margin-top: 30px">
                    <div id="max_wrap_item_policy_inner">
                    <div>
                        <img
                        src="https://images.3dsellers.com/listing-designer/policy/delivery2.svg"
                        style="width: 60px; margin-right: 10px"
                        alt=""
                        />
                        <span> FAST UK DELIVERY </span>
                    </div>
                    <div>
                        <img
                        src="https://images.3dsellers.com/listing-designer/policy/airport.svg"
                        style="width: 60px; margin-right: 10px"
                        alt=""
                        />
                        <span> FAST GLOBAL SHIPPING </span>
                    </div>
                    <div>
                        <img
                        src="https://images.3dsellers.com/listing-designer/policy/delivery1.svg"
                        style="width: 60px; margin-right: 10px"
                        alt=""
                        />
                        <span> 60 DAY RETURNS </span>
                    </div>
                    </div>
                </div>
            </div>

            <div id='max_tabs_wrap_upper' >
                <style>
                    #max_tabs_wrap input:checked + label {
                        background-color: #000;
                        color: #fff;
                        border: 1px solid #000;
                    }

                    #max_tabs_wrap input {
                        display: none;
                    }

                    #max_tabs_wrap label:hover {
                        background-color: #ddd;
                        color: #000;
                        border: 1px solid #000;
                        transition: 0.3s ease-in-out;
                    }
                    #max_tabs_wrap label {
                        display: inline-block;
                        margin: 0 0 -1px;
                        padding: 8px 25px;
                        font-weight: 700;
                        text-align: center;
                        background-color: #fff;
                        color: #000;
                        cursor: pointer;
                        border: 1px solid #000;
                        margin: 0 0 4px 0;
                        font-size: 14px;
                    }
                    #max_tabs_wrap section {
                        display: none;
                        margin: 15px 0 0 0;
                        padding: 30px 50px 50px 50px;
                        border: 1px solid #000;
                        overflow: hidden;
                        overflow-y: auto;
                        font-family: inherit;
                        color: black;
                        font-size: 14px;
                        font-weight: 600;
                        text-decoration: none !important;
                        font-style: Montserrat;
                    }
                    #max_tabs_inner p {
                        font-size: 14px; 
                        font-weight: 700;
                    }
                    #max_condition_guide:checked ~ #max_conition_guider_content,
                    #max_sizing_guide:checked ~ #max_sizing_guide_content,
                    #max_delivery_info:checked ~ #max_delivery_info_content,
                    #max_return_info:checked ~ #max_return_info_content {
                        display: block;
                    }
                    @media screen and (max-width: 768px) {
                        #max_tabs_wrap label {
                            width: 100%;
                            padding:5px 0;
                            margin-top: 8px !important;
                        }
                    }
                </style>
                <div id='max_tabs_wrap' style="margin-top:30px;" >
                    <div id='max_tabs_inner'  >
                        <input id='max_condition_guide' type='radio' name='tabs' checked=''>
                        <label for='max_condition_guide' class='transition-300'>CONDITION GUIDE</label>

                        <input id='max_sizing_guide' type='radio' name='tabs'>
                        <label for='max_sizing_guide' class='transition-300'>SIZING GUIDE</label>

                        <input id='max_delivery_info' type='radio' name='tabs'>
                        <label for='max_delivery_info' class='transition-300'>DELIVERY INFO</label>

                        <input id='max_return_info' type='radio' name='tabs'>
                        <label for='max_return_info' class='transition-300'>RETURNS INFO</label>

                        <section id='max_conition_guider_content'>
                            <p>$Condition_Guide</p>
                        </section>

                        <section id='max_sizing_guide_content'>
                            <p> $Sizing_Guide </p>
                        </section>

                        <section id='max_delivery_info_content'>
                            <p> $Delivery_Info </p>
                        </section>

                        <section id='max_return_info_content'>
                            <p> $Returns_Info </p>
                        </section>
                    </div>
                </div>
            </div>

        HTML;

        return $html;
    }

    protected function roundPrice($price)
    {
        $price = (float) round($price);
        $price = $price - 0.01;
        return $price;
    }
    protected function upperCaseFirstWord($title)
    {
        $t_arr = explode(' ', trim($title));
        $t_arr[0] = strtoupper($t_arr[0]);
        $title = implode(' ', $t_arr);
        return $title;
    }



    protected function queryForCategory($product)
    {
        $query = $this->matchTagsAndTitle($product);
        array_push($query, HelperService::extractGender($product['tags']));
        array_push($query, $product['product_type']);

        $query = array_unique($query);
        $query = implode(':', $query);
        return $query;
    }

    protected function matchTagsAndTitle($product)
    {
        $title = explode(' ', strtolower($product['title']));
        $tags = explode(',', strtolower($product['tags']));

        foreach ($tags as $k => $val) {
            if (str_contains($val, 'http') || str_contains($val, 'stock') || preg_match('/\b\d+\b/', $val)) {
                unset($tags[$k]);
            }
        }

        $query = array_merge($title, $tags);
        return $query;
    }



    public function store_failed_sync($shopify_product_id, $errors, $sh_title = '', $sh_desc = '', $title = '', $desc = '')
    {
        try {

            DB::table('failed_ebay_sync_items')->updateOrInsert(
                [
                    'shopify_product_id' => strval($shopify_product_id)
                ],
                [
                    'errors' => json_encode($errors),
                    'shopify_title' => $sh_title,
                    'shopify_body_html' => $sh_desc,
                    'ebay_title' => $title,
                    'ebay_body_html' => $desc,
                    'created_at' => now(),
                ]
            );
            Log::channel('sync_products')->info('[Saving Failed Product]  ' . $shopify_product_id);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->info('[CANNOT Save Failed Product]:' . $th->getMessage());
        }
    }

    public function get_item_specifics($chatgpt_item_specifics, $product)
    {
        $department = ucfirst(HelperService::extractGender($product['tags']));
        $accents = str_contains(strtolower($product['title']), 'embroidered') ? 'Embroidered' : 'Logo';
        $sku = (string) $product['variants'][0]['sku'];

        $final_item_specifics = [
            'Accents' => $accents,
            'Type' => $product['product_type'],
            'Size Type' => 'Regular',
            'Season' => 'Autumn, spring, winter, summer',
            'Fit' => 'Regular',
        ];
        foreach ($this->getRecommendedItemSpecifics($product) as $key => $value) {
            $final_item_specifics[$key] = $value;
        }

        if (str_contains($this->new_title, 'sweatshirt')) {
            $final_item_specifics['Accents'] = 'Logo';
        }

        $invalid_values = ['not specified', 'unknown', 'n/a'];
        // ChatGPT created item specifcs
        foreach ($chatgpt_item_specifics as $key => $value) {
            if (strtolower($key) == 'color') $key = 'Colour';
            if (is_array($value)) {
                Log::channel('sync_products')->info('Specfic value is array: ' . print_r($value, true));
                continue;
            }
            if (!in_array(strtolower($value), $invalid_values)) {
                $final_item_specifics[$key] = $value;
            }
        }
        $final_item_specifics['Brand'] = $product['vendor'];
        $final_item_specifics['Department'] = $department;
        $final_item_specifics['MPN'] = $sku;
        $final_item_specifics['Vintage'] = 'Yes';
        $final_item_specifics['Country/Region of Manufacture'] = 'UK';
        $final_item_specifics['Language'] = 'English';

        // User Saved Item Specifics checked. 
        foreach ($this->check_item_specifics($product) as $key => $value) {
            if (empty($value)) {
                if (isset($final_item_specifics[$key])) unset($final_item_specifics[$key]);
            } else {
                $final_item_specifics[$key] = $value;
            }
        }
        // All custom check
        if (isset($final_item_specifics['Waist'])) {
            $waist = $final_item_specifics['Waist'];
            if (is_numeric($waist)) {
                $waist = $waist . ' in';
            }
            unset($final_item_specifics['Waist']);
            $final_item_specifics['Waist Size'] = $waist;
        }

        if (isset($final_item_specifics['Size']) && is_numeric($final_item_specifics['Size'])) {
            $final_item_specifics['Size'] = $final_item_specifics['Size'] . ' in';
        }

        if (!is_numeric($final_item_specifics['Size'])) {
            $ebay_sizes = config('ebay_sizes');
            $size = strtolower($final_item_specifics['Size']);
            foreach ($ebay_sizes as $size_key => $value) {
                if (str_contains($size, $size_key)) {
                    $final_item_specifics['Size'] = $value;
                }
            }
        }
        if (!isset($final_item_specifics['Outer Shell Material'])) {
            if (isset($final_item_specifics['Material'])) {
                $final_item_specifics['Outer Shell Material'] = $final_item_specifics['Material'];
            }
        }

        // Log::channel('sync_products')->info('Item specifcs: ' . print_r($final_item_specifics, true));
        // exit;
        return $final_item_specifics;
    }

    public function check_item_specifics($product)
    {
        // $itemspecifics = config('itemspecifics');
        $itemspecifics = $this->get_stored_itemspecfics();
        $content = strtolower($this->new_title . $product['body_html']);
        $item_specifics = [];
        foreach ($itemspecifics as $aspect_name => $item_aspect) {
            if (empty($item_aspect)) {
                $item_specifics[$aspect_name] = '';
                continue;
            }
            foreach ($item_aspect as $aspect_object) {
                foreach ($aspect_object['keys'] as $aspect_key) {

                    if (empty($aspect_object['value'])) {
                        unset($item_specifics[$aspect_name]);
                        continue;
                    }

                    if (str_contains($content, strtolower($aspect_key))) {

                        if (isset($item_specifics[$aspect_name])) {
                            $item_specifics[$aspect_name] = $item_specifics[$aspect_name] . ', ' . $aspect_object['value'];
                        } else {
                            $item_specifics[$aspect_name] = $aspect_object['value'];

                            break;
                        }
                    }
                }
            }
        }
        // Log::channel('sync_products')->info('checked items specifics: '. print_r($item_specifics, true));
        return $item_specifics;
    }

    public function get_stored_itemspecfics()
    {
        $updated_item_specfics = [];
        $itemspecifics = ItemSpecific::all()->toArray();
        foreach ($itemspecifics as $aspect) {
            $updated_item_specfics[$aspect['aspect_name']] = json_decode($aspect['aspect_values'], true);
        }
        return $updated_item_specfics;
    }

    public function getRecommendedItemSpecifics($product)
    {
        $token = HelperService::get_oauth_token();
        $categoryId = $this->current_category_id;
        $url = "https://api.ebay.com/commerce/taxonomy/v1/category_tree/3/get_item_aspects_for_category?category_id={$categoryId}";
        $headers = [
            'Authorization' => "Bearer " . $token,
            'Content-Type' => 'application/json'
        ];
        $response = Http::withHeaders($headers)->get($url);
        // exit;
        $itemSpecifics = $response->json();
        if (!isset($itemSpecifics['errors']) || isset($itemSpecifics['aspects'])) {
            return $this->matchItemSpecifics($itemSpecifics, $product);
        } else {
            Log::channel('sync_products')->info('Recommended Item spcifics request error response: ' . print_r($response->json(), true));
        }
        return null;
    }

    private function matchItemSpecifics($itemSpecifics, $product)
    {
        $matchedSpecifics = [];
        $content = strtolower($this->new_title . $product['body_html']);

        foreach ($itemSpecifics['aspects'] as $aspect) {
            // Log::channel('sync_products')->info('aspectValues: ' . print_r($aspect['aspectValues'], true));
            // exit;
            $aspectName = $aspect['localizedAspectName'];
            if (isset($aspect['aspectValues'])) {
                foreach ($aspect['aspectValues'] as $value) {
                    if (str_contains($content, strtolower($value['localizedValue']))) {
                        if (isset($matchedSpecifics[$aspectName])) {
                            $matchedSpecifics[$aspectName] =  $matchedSpecifics[$aspectName] . ', ' . $value['localizedValue'];
                        } else {
                            $matchedSpecifics[$aspectName] = $value['localizedValue'];
                        }
                    }
                }
            }
        }

        // Log::channel('sync_products')->info('Found Item specifics: ' . print_r($matchedSpecifics, true));
        return $matchedSpecifics;
    }

    public function update_item_title($product, $item_id)
    {
        $token = HelperService::get_oauth_token();
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
        ];
        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
        XML;

        $title =  $this->chatGPTService->title($product);
        if (empty($title)) {
            $title =  $this->chatGPTService->title($product);
        }
        $title = $this->upperCaseFirstWord($title);
        $title = $this->escapeXml($title);
        $requestxml .= <<<XML
                    <Item>
                        <ItemID>$item_id</ItemID>
                        <Title>$title</Title>
                    </Item>
            XML;
        $requestxml .= <<<XML
            </ReviseItemRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('[Title Update Request Error]: ' . $th->getMessage());
            return false;
        }
        $responseXml = simplexml_load_string($response->body());

        if ($responseXml->Ack == 'Failure') {
            Log::channel('sync_products')->error('[Title Update Error]: ' . print_r($responseXml, true));
            return false;
        } else {
            Log::channel('sync_products')->info('Title Updated Successfully.');
            return true;
        }
    }

    public function update_item_specifics($product, $item_id)
    {

        $token = HelperService::get_oauth_token();
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
        ];
        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
        XML;

        $queryForCat = $this->queryForCategory($product);
        $category_id = $this->category_client->get_stored_category($product);
        if (!$category_id) {
            $category =  $this->category_client->GetSuggestedCategories($queryForCat);
            $category_id = $category['id'];
        }
        $this->current_category_id = $category_id;

        $chatGPT_item_specfics = $this->chatGPTService->item_specifics($product);
        $item_specifics = $this->get_item_specifics($chatGPT_item_specfics, $product);

        $requestxml .= <<<XML
                    <Item>
                        <ItemID>$item_id</ItemID>
        XML;
        $requestxml .= <<<XML
                <ItemSpecifics>
            XML;

        foreach ($item_specifics as $key => $value) {
            $requestxml  .= <<<XML
                    <NameValueList> 
                        <Name> $key </Name>
                        <Value>$value</Value> 
                    </NameValueList>
            XML;
        }

        $requestxml  .= <<<XML
                </ItemSpecifics>
            </Item>
            </ReviseItemRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error('[Item Specifics Update Request Error]: ' . $th->getMessage());
            return false;
        }
        $responseXml = simplexml_load_string($response->body());

        if ($responseXml->Ack == 'Failure') {
            Log::channel('sync_products')->error('[Item Specifics Update Error]: ' . print_r($responseXml, true));
            return false;
        } else {
            Log::channel('sync_products')->info('Item Specifics Updated Successfully.');
            return true;
        }
    }
    public function update_item_stock($new_stock, $item_id)
    {

        $token = $this->auth_n_auth_token;
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
        ];
        $requestxml = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                    <Item>
                        <ItemID>$item_id</ItemID>
                        <Quantity>$new_stock</Quantity>
                    </Item>
            </ReviseItemRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            throw new Error($th->getMessage());
        }
        $responseXml = simplexml_load_string($response->body());

        if ($responseXml->Ack == 'Failure') {
            return $responseXml;
        } else {
            return true;
        }
    }


    public function update_item_fields($field_xml, $item_id)
    {
        $token = HelperService::get_oauth_token();
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
        ];
        $requestxml = <<<XML
            <?xml version="1.0" encoding="utf-8" ?>
            <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                <Version>967</Version>
                <ErrorLanguage>en_US</ErrorLanguage>
                <WarningLevel>High</WarningLevel>
                    <Item>
                        <ItemID>$item_id</ItemID>
                        $field_xml
                    </Item>
            </ReviseItemRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error("[ $field_xml Update Request Error]: " . $th->getMessage());
            return false;
        }
        $responseXml = simplexml_load_string($response->body());

        if ($responseXml->Ack == 'Failure') {
            Log::channel('sync_products')->error("[ $field_xml Update Error]: " . print_r($responseXml, true));
            return false;
        } else {
            Log::channel('sync_products')->info(" $field_xml Updated Successfully.");
            return $responseXml;
        }
    }
    public function getTotalItemCount()
    {
        $token = HelperService::get_oauth_token();
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

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            Log::channel('sync_products')->error(" [GetMyeBaySelling] [Error]: " . $th->getMessage());
            return false;
        }
        $responseXml = simplexml_load_string($response->body());
        if ($responseXml->Ack == 'Failure') {
            Log::channel('sync_products')->error("[GetMyeBaySelling]: " . print_r($responseXml, true));
            return false;
        } else {
            $activeCount = (int) $responseXml->ActiveList->PaginationResult->TotalNumberOfEntries;
            return $activeCount;
        }
    }

    public function get_order($order_id)
    {

        $token = HelperService::get_oauth_token();

        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $token"
            ])->get("https://api.ebay.com/sell/fulfillment/v1/order?orderIds=$order_id");

            return $response;

        } catch (\Throwable $th) {
            Log::channel('ebay_webhook')->error('Error: Unable get order. error message: ' .  $th->getMessage());
        }
    }

    public function EndItem($item_id){

        $token = HelperService::get_oauth_token();
        $request_headers = [
            'X-EBAY-API-SITEID' => 3,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => 967,
            'X-EBAY-API-IAF-TOKEN' => $token,
            'X-EBAY-API-CALL-NAME' => 'EndItem',
        ];
        $requestxml = <<<XML
           <?xml version="1.0" encoding="utf-8"?>
            <EndItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                <RequesterCredentials>
                    <eBayAuthToken>$token</eBayAuthToken>
                </RequesterCredentials>
                    <ErrorLanguage>en_US</ErrorLanguage>
                    <WarningLevel>High</WarningLevel>
                <ItemID>$item_id</ItemID>
                <EndingReason>NotAvailable</EndingReason>
            </EndItemRequest>
        XML;

        $response = null;
        try {
            $response = Http::withHeaders($request_headers)->timeout(70)->send('POST', $this->ebay_request_url, [
                'body' => $requestxml,
            ]);
        } catch (\Throwable $th) {
            throw new Error($th->getMessage());
        }
        $responseXml = simplexml_load_string($response->body());
        if ($responseXml->Ack == 'Failure') {
            return $responseXml;
        } else {
          return true;
        }

    }

}
