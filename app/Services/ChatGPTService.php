<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatGPTService
{

    protected $maxRetries = 3;
    protected $retryDelay = 5;
    protected $client;
    protected $apiKey;
    protected $url = 'https://api.openai.com/v1/chat/completions';
    

    public function __construct()
    {
        // $this->client = new Client();
        $this->apiKey = env('OPEN_AI_API_SECRET_KEY');
    }
    public function title($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        
        $prompt = "I need you to generate the best ebay title from the following product title, description and tags.    
            \n\n
            Please remember following points while creating title for ebay: \n\n 
            Please only follow this pattern for title [brand gender product type colour size].\n
            If applicable, the title must include the gender for the product.\n
            Write the first occurance of brand name in uppercase.\n
            The title with 55 characters will be ideal.\n
            Title should not have vintage quality or (missing sizing label). \n\
            The title should not exceed 65 characters.\n
            Please do not add measurements in title armpit, waist size etc.\n 
            Avoid any improper words or phrases that may violate eBay policies. \n\n

            Original title: $product[title] \n\n 
            Original description: $product[body_html] \n\n 
            Original tags: $product[tags] \n\n 

            Example:\n
            ADIDAS Men's Ultimate Hoodie Pullover Black Large\n 
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.5,
            'max_tokens' => 100
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        // Log::channel('chat_gpt')->info(' Title: ' . print_r($body, true));
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return $content;
        }
    }

    public function description($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        // Description should have some small html template for ebay description. \n
        // The HTML template should be mobile responsive and not create large template. \n\n
        // The description should include the best ebay keywords for the item. \n

        // You can modify this line (Item is in good used condition) words. \n\n
        
        $prompt = "I need you to generate the ebay description from the following product title, description.    
            \n\n
            Please remember following points while creating description for ebay: \n\n 
            Follow the example and remember to place this line (Item is in good used condition) in start. \n
            Only provide Size, Armpit To Armpit, Armpit To Cuff, and Armpit To Hem. \n
            Please be specific not add anything by your self. \n
            Avoid any improper words or phrases that may violate eBay policies. \n
            If item cateogry is shorts or trousers you should use waist, Outside leg, inside leg, leg opening, etc instead of Armpit To Armpit, Collar To Hem, Armpit To Cuff, etc. \n\n

            Original title: $product[title] \n\n 
            Original description: $product[body_html] \n\n 

            Example:\n
            <div>
                <style>
                    #max_short_description div#max_short_description_inner{
                        padding: 25px;
                        border: 1px solid #000;
                        overflow: hidden;
                        overflow-y: auto;
                        font-family: inherit;
                        color: black;
                        display: flex;
                        flex-direction:column;    
                    }
                </style>
                <div id='max_short_description' style='margin-top: 30px;'>
                    <div id='max_short_description_inner' vocab='https://schema.org/' typeof='Product'>
                        <span property='description' > Item is in good used condition. <br/>
                        >Size: S <br/>
                        >Armpit To Armpit: 17\" <br/>
                        >Armpit To Cuff: 25\" <br/>
                        >Armpit To Hem: 31\" <br/>
                        </span>
                    </div>
                </div>
            </div>
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        // Log::channel('chat_gpt')->info(' description: ' . print_r($body, true));
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return $content;
        }
    }

    public function item_used_condition($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        $sample_array = [  
            'B Grade - Marks throughout' , 
            'A Vintage Quality' ,
            'Colour Fading -See photos' ,
            'Faulty zip (repairable)' ,
            'Multiple stains - B Grade' ,
            'Multiple stains and holes - B Grade' ,
            'Print fading - See photos' ,
            'Small mark on arm' ,
            'Small mark on back' ,
            'Small mark on cuff' ,
            'Small mark on front' ,
            'Small mark on front & arm' ,
            'Small mark on front & back' ,
            'Small mark on hoodie' ,
            'Small watermark on front' ,
            'Tiny hole on arm (see photos)' ,
            'Tiny hole on back (see photos)' ,
            'Tin hole on front (see hotos)' ,
            'Stain mark on front side',
        ];
        $bodyHTML = strtolower($product['body_html']);
        foreach ($sample_array as  $condition) {
            if(str_contains($bodyHTML, strtolower(trim($condition)))){
                return $condition;
            }
        }

        $prompt = "I need you to extract product quality from the following product description.    
            \n\n
            Please remember following points while extracting product quality info: \n\n
            if you found only send that exact not add something in that. \n
            You will see in desciption mostly like this line Quality: A Vintage Quality \n
            If you cannot found like sample line then search carefully in decription otherwise send like (A vintage quality). \n\n

            Original description: $product[body_html] \n\n 

            These are mostly possible that will come I just want to let you know \n\n
            A Vintage Quality \n
            B Grade - Marks throughout \n 
            Colour Fading -See photos \n
            Faulty zip (repairable) \n
            Multiple stains - B Grade \n
            Multiple stains and holes - B Grade \n
            Print fading - See photos \n
            Small mark on arm \n
            Small mark on back \n
            Small mark on cuff \n
            Small mark on front \n
            Small mark on front & arm \n
            Small mark on front & back \n
            Small mark on hoodie \n
            Small watermark on front \n
            Tiny hole on arm (see photos) \n
            Tiny hole on back (see photos) \n
            Tin hole on front (see hotos) \n
            \n\n
            Example:\n
              B Grade - marks trhoughout
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        // Log::channel('chat_gpt')->info(' used conidtion: ' . print_r($body, true));
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return $content;
        }
    }

    public function item_specifics($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        
        $prompt = "
            PLease act like a master of clothing brand with 20 years of experience in marketing for clothing,
            I need you to generate the ebay maximum possible item specifics from the following product title, description and tags.\n\n

            Please remember following points while creating item specifics for ebay: \n\n 

            Provide item specifics in the format of a JSON object. \n 

            Here are some example item specifics Condition, Material, MPN, Size, Department, Accents, Theme, Season, Performance/Activity, Occasion, Country/Region of Manufacture, Armpit To Armpit, Armpit To Cuff, Collar To Hem, Fabric Type, Era, Colour, Pattern, Sleeve Length, Style, Type, Collar Style, Fit, Outer Shell Material, closure, Neckline, and Size Type. Please try to extract all possible item specifcs. \n

            if you unable to find value for required item specific then don't include that item specific but make sure to add required specifics while putting a guess value based on the rest of the description of the items. For example if the type is a jacket and Outer Shell Material is a required specific then assume any value like leather, fleece and put in there and same goes for t-shirts like material coton and for jeans season and all seasons. but please make sure don't show any value like not specified, N/A or Unknown it should be a real type of value.\n

            There should not be any item specific empty value and must provide all possible item specifics for given category or product type. \n\

            Please carefully read the provided description and title it will help you to find maximum possible item specifics and also you will able to estimate their suitable value. \n

            Use unit (in) for Armpit To Armpit, Collar To Hem, Armpit To Cuff, Waist Size, Outside leg, inside leg, leg opening, any other relavent. \n
            
            You should know that If item cateogry is shorts or trousers you should use Waist Size, Outside leg, inside leg, leg opening, etc instead of Armpit To Armpit, Collar To Hem, Armpit To Cuff, etc. \n\n
            Style is important item specifics do not miss it remember to find it. \n

            Remember Country/Region of Manufacture will be always UK.\n\n


            Original title: $product[title] \n\n 
            Original description: $product[body_html] \n\n 
            Original tags: $product[tags] \n\n

            Example:\n
            {\n
                \"Condition\": \"New\",\n
                \"Material\": \"Cotton\",\n
                \"Theme\": \"Outdoor\",\n
                \"Size\": \"XL\",\n
                \"Fit\": \"Regular\",\n
                \"Style\": \"Slim\",\n
                \"Sleeve Length\": \"Long Sleeve\",\n
                \"Colour\": \"Blue\",\n
                \"Pattern\": \"Solid\",\n
                \"Closure\": \"Button\",\n
                \"Type\": \"Sweatshirt\",\n
                \"Occasion\": \"Activewear\",\n
                \"Neckline\": \"Crew Neck\",\n
                \"Fabric Type\": \"Fleece\",\n
                \"Garment Care\": \"Machine Washable\",\n
                \"Armpit To Armpit\": \"27 in\",\n
                \"Collar To Hem\": \"27 in\",\n
                \"Country/Region of Manufacture\": \"UK\",\n

                // Add other relevant required specifics\n
            }\n
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        if(isset($body['choices'][0]['message']['content']['error'])){
            // Log::channel('chat_gpt')->info(' Item specifics: ' . print_r($body, true));
        }
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return json_decode($content, true);
        }
    }
    public function categoryQuery($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        
        $prompt = "I using ebay trading API and this GetSuggestedCategories method to get suggested categories I need you to generate keywords to send with request. I have provided the following product title, description and tags.    
            \n\n
            Please remember following points while creating query for GetSuggestedCategories ebay: \n\n 
            Keyword Query must follow ebay API rules. \n\n
            Only provide keywords in this form (Art:Prints:Antique:Architecture:Men) and in one line. \n
            Gender should also be added. \n\n

            Original title: $product[title] \n\n 
            Original description: $product[body_html] \n\n 
            Original tags: $product[tags] \n\n

            Example:\n
            Art:Prints:Antique (Pre-1900):Architecture:Women
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        // Log::channel('chat_gpt')->info(' Category Query: ' . print_r($body, true));
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return $content;
        }
    }

    public function advanced_description($product){
        $api_key = $this->apiKey;
        $url = $this->url;
        return [
            'Condition Guide' => "
                All of our items are authentic vintage/second hand pieces so naturally minor signs of wear such as small stains, small pulls/pin holes and minor marks can be expected.
                <br><br>
                We think this just adds to their charm and uniqueness! We do our very best to find any major faults and aim to include them in the photos so please look at them carefully.
                <br><br>
                Please see the item specifics/details above for the exact measurements and more information.
            ",
            'Sizing Guide' => "
                VINTAGE CLUB stocks a large variety of clothing from a wide range brands. This means that all of our sizes will vary slightly. Vintage items from different eras can also be noticeably different in size, even if the label says they are the same. 
                <br><br>
                For each item we will state the size that is shown on the label. We will make a note in the product description if the item is no longer true to size due to age. Specific measurements are available upon request. Please message us with the exact item details you require and we will get back to you as soon as we can.
            ",
            'Delivery Info' => "
                We use Royal Mail to deliver all of our items. UK shipping is free and sent via a Royal Mail 48hr tracked service.
                <br><br>
                We send all of our orders out on the same working day if you order before 2pm Monday-Friday. All standard UK items should arrive within 2 working days.
                <br><br>
                Express UK shipping is £1.95 and items should arrive within 1 working day.
                <br><br>
                We ship worldwide via the eBay Global Shipping Programme (GSP).
            ",
            'Returns Info' => "
                All items can be returned and a refund will be issued, provided they arrive at our address within 60 days of receipt of purchase.
                <br><br>
                Items returned after that period will not be eligible for a refund and there will be a charge for returning the goods to the sender. Please note, we can only refund the original cost of the item, not any postage costs.
                <br><br>
                All items will be inspected upon return. They must be unused, still tagged and in the same condition you received them in. Your return must also include your original printed invoice. Any item returned to us in in an unsuitable condition will not be issued with a refund.
                <br><br>
                Once we have received the unwanted goods, a refund will be processed via the original payment method. Payments may take 3-5 working days to process as standard. All of our refunds are completed via eBay.
                <br><br>
                Please send all returns via tracked mail as we cannot be held responsible for lost items.
            "
        ];



        $prompt = "I need you to generator the best ebay advanced description from the following product title and description.    
            \n\n
            Please remember following points while creating advanced description for ebay: \n\n 
            Provide an advanced description with the following keys and values: ( Condition Guide, Sizing Guide, Delivery Info, Returns Info ). \n\n

            Original title: $product[title] \n\n 
            Original description: $product[body_html] \n\n 

            Example:\n
            {\n
                \"Condition Guide\": \"
                    All of our items are authentic vintage/second hand pieces so naturally minor signs of wear such as small stains, small pulls/pin holes and minor marks can be expected.
                    \n\n
                    We think this just adds to their charm and uniqueness! We do our very best to find any major faults and aim to include them in the photos so please look at them carefully.
                    \n\n
                    Please see the item specifics/details above for the exact measurements and more information.
                \",

                \"Sizing Guide\": \"
                    VINTAGE CLUB stocks a large variety of clothing from a wide range brands. This means that all of our sizes will vary slightly. Vintage items from different eras can also be noticeably different in size, even if the label says they are the same. 
                    \n\n
                    For each item we will state the size that is shown on the label. We will make a note in the product description if the item is no longer true to size due to age. Specific measurements are available upon request. Please message us with the exact item details you require and we will get back to you as soon as we can.
                \",

                \"Delivery Info\": \"
                    We use Royal Mail to deliver all of our items. UK shipping is free and sent via a Royal Mail 48hr tracked service.
                    \n\n
                    We send all of our orders out on the same working day if you order before 2pm Monday-Friday. All standard UK items should arrive within 2 working days.
                    \n\n
                    Express UK shipping is £1.95 and items should arrive within 1 working day.
                    \n\n
                    We ship worldwide via the eBay Global Shipping Programme (GSP).
                \",

                \"Returns Info\": \"
                    All items can be returned and a refund will be issued, provided they arrive at our address within 60 days of receipt of purchase.
                    \n\n
                    Items returned after that period will not be eligible for a refund and there will be a charge for returning the goods to the sender. Please note, we can only refund the original cost of the item, not any postage costs.
                    \n\n
                    All items will be inspected upon return. They must be unused, still tagged and in the same condition you received them in. Your return must also include your original printed invoice. Any item returned to us in in an unsuitable condition will not be issued with a refund.
                    \n\n
                    Once we have received the unwanted goods, a refund will be processed via the original payment method. Payments may take 3-5 working days to process as standard. All of our refunds are completed via eBay.
                    \n\n
                    Please send all returns via tracked mail as we cannot be held responsible for lost items.
                \";
            }\n
        ";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }
        $body = $response->json();
        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];
            return json_decode($content, true);
        }
    }

    public function send_to_chatgpt($title, $description)
    {
        $api_key = $this->apiKey;
        $url = 'https://api.openai.com/v1/chat/completions';

        $prompt = "Generate the best eBay title, description, item specifics, and advanced description for the following product:\n\n";
        $prompt .= "Title: $title\n";
        $prompt .= "Description: $description\n";
        $prompt .= "Ensure the title includes the best eBay keywords for the item.\n";
        $prompt .= "Write the brand name in full caps.\n";
        $prompt .= "The title must not exceed 74 characters.\n";
        $prompt .= "Provide item specifics in the format of a JSON object.\n";
        $prompt .= "The item specifics should include keys such as Condition, Material, Brand, MPN, Size, Department, Accents, Theme, Season, Performance/Activity, Occasion, Country/Region of Manufacture, Armpit To Armpit, Armpit To Cuff, Collar To Hem, Fabric Type, Era, Colour, Pattern, Sleeve Length, Style, Type, Collar Style, Fit, and Size Type.\n";
        $prompt .= "Provide measurements (Armpit To Armpit, Armpit To Cuff, Collar To Hem) without units like inches.\n";  
        $prompt .= "Provide an advanced description with the following keys and values:\n";
        $prompt .= "Condition Guide, Sizing Guide, Delivery Info, Returns Info.\n";
        $prompt .= "Example:\n";
        $prompt .= "Title: Best Title Example\n";
        $prompt .= "Description: This is an example description.\n";
        $prompt .= "Item Specifics: {\n";
        $prompt .= "  \"Condition\": \"New\",\n";
        $prompt .= "  \"Material\": \"Cotton\",\n";
        $prompt .= "  \"Brand\": \"BRAND NAME\",\n";
        $prompt .= "  \"Theme\": \"Outdoor, Classic, Retro\",\n";
        $prompt .= "  \"Size\": \"XL\",\n";
        $prompt .= "  // Add other specifics as relevant\n";
        $prompt .= "}\n";
        $prompt .= "Advanced Description: {\n";
        $prompt .= "  \"Condition Guide\": \"All of our items are authentic vintage/second hand pieces so naturally minor signs of wear such as small stains, small pulls/pin holes and minor marks can be expected. We think this just adds to their charm and uniqueness! We do our very best to find any major faults and aim to include them in the photos so please look at them carefully. Please see the item specifics/details above for the exact measurements and more information.\",\n";
        $prompt .= "  \"Sizing Guide\": \"GO THRiFT stocks a large variety of clothing from a wide range brands. This means that all of our sizes will vary slightly. Vintage items from different eras can also be noticeably different in size, even if the label says they are the same. For each item we will state the size that is shown in the label. We will make a note in the product description if the item is no longer true to size due to age. Specific measurements are available upon request. Please message us with the exact item details you require and we will get back to you as soon as we can.\",\n";
        $prompt .= "  \"Delivery Info\": \"We use Royal Mail to deliver all of our items. UK shipping is free and sent via a Royal Mail 48hr tracked service. We send all of our orders out on the same working day if you order before 2pm Monday-Saturday. All standard UK items should arrive within 2-3 working days. Express UK shipping is £1.95 and items should arrive within 1 working day. We ship worldwide via the eBay Global Shipping Programme (GSP).\",\n";
        $prompt .= "  \"Returns Info\": \"All items can be returned and a refund will be issued, provided they arrive at our address within 60 days of receipt of purchase. Items returned after that period will not be eligible for a refund and there will be a charge for returning the goods to the sender. Please note, we can only refund the original cost of the item, not any postage costs. All items will be inspected upon return. They must be unused, still tagged and in the same condition you received them in. Your return must also include your original printed invoice. Any item returned to us in an unsuitable condition will not be issued with a refund. Once we have received the unwanted goods, a refund will be processed via the original payment method. Payments may take 3-5 working days to process as standard. All of our refunds are completed via eBay. Please send all returns via tracked mail as we cannot be held responsible for lost items.\"\n";
        $prompt .= "}\n";

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ])->timeout(30)->post($url, $data);

        if ($response->failed()) {
            return $response->body();
        }

        $body = $response->json();

        if (isset($body['choices'][0]['message']['content'])) {
            $content = $body['choices'][0]['message']['content'];

            $result = json_decode($content, true);
            // Log::channel('sync_products')->info('Chat GPT response: ' . $content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $result = [
                    'title' => '',
                    'description' => '',
                    'item_specifics' => [],
                    'advanced_description' => [
                        'Condition Guide' => '',
                        'Sizing Guide' => '',
                        'Delivery Info' => '',
                        'Returns Info' => ''
                    ]
                ];

                if (preg_match('/Title:\s*(.*)/', $content, $matches)) {
                    $result['title'] = trim($matches[1]);
                }

                if (preg_match('/Description:\s*(.*)/', $content, $matches)) {
                    $result['description'] = trim($matches[1]);
                }

                if (preg_match('/Item Specifics:\s*(\{.*?\})\s*(?:Advanced Description:|$)/s', $content, $matches)) {
                    $specifics = json_decode($matches[1], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['item_specifics'] = $specifics;
                    }
                }

                if (preg_match('/Advanced Description:\s*(\{.*\})/s', $content, $matches)) {
                    $advanced = json_decode($matches[1], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['advanced_description'] = $advanced;
                    }
                }
            }

            return [
                'title' => $result['title'],
                'description' => $result['description'],
                'item_specifics' => $result['item_specifics'],
                'advanced_description' => $result['advanced_description']
            ];
        }

        return null;
    }
    public function convertJson($jdata)
    {

        $prompt = "I have a JSON-like string that needs to be converted into a valid JSON format suitable for decoding and usage in PHP. The string is as follows:\n\n";
        $prompt .= "\"" . $jdata . "\"\n\n";
        $prompt .= "Please provide the valid JSON string.";

        // Make a request to OpenAI's API
        $client = new Client();
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4',  // or 'gpt-3.5-turbo' if you are using GPT-3.5
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an assistant.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 1000,
            ],
        ]);

        $responseBody = json_decode($response->getBody(), true);

        // Extract the valid JSON string from the response
        $validJsonString = $responseBody['choices'][0]['message']['content'];

        $startPos = strpos($validJsonString, '{');
        if ($startPos !== false) {
            $endPos = strrpos($validJsonString, '}') + 1;
            $jsonData = json_decode(substr($validJsonString, $startPos, $endPos - $startPos), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Access the decoded JSON data
                return $jsonData;
            } else {
                echo "Error: Could not decode JSON string.";
            }
        } else {
            echo "No JSON data found.";
        }
        return $validJsonString;
    }
}


/**
 * 
 * 
 * I need you to generator the best ebay title from the following product title.
 *  The title should include the best ebay keywords for the item.
 * write the brand name in full caps
 *  Dont write Vintage in full caps
 * The title must not exceed 79 characters:
 * Original title:
 */

/**
 * title 
 * description
 * item_specific
 * Advance description
 */