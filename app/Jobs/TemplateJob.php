<?php

namespace App\Jobs;

use App\Models\ItemSource;
use App\Services\EbayItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SebastianBergmann\Template\Template;

class TemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $url;
    protected $endpoint = '/api/ebay/apply-template/';
    protected $update_endpoint = '/api/ebay/update-template/';
    protected $ebay_item_ids;
    /**
     * Create a new job instance.
     */
    public function __construct($ebay_item_ids = [])
    {
        $this->url = env('TEMPLATE_BUILDER_URL', 'https://ebay-template-builder.vintageclubmysteryboxsoftware.com');
        $this->ebay_item_ids = $ebay_item_ids;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->ebay_item_ids as  $item_id) {
            $request_url = $this->url . $this->endpoint . $item_id;
            // Log::channel('template_builder')->info('Request URL: ' . $request_url);
            $itemSource = ItemSource::where('ebay_item_id', strval($item_id))->first();

            if ($itemSource->template_applied == 1) {
                $this->update_template($item_id);
                continue;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer 1|RB78qoDgH4Vq8s9LqFGsqeo6Ninb79eLWNJsJSBu907ac95c',
                'Accept' => 'application/json'
            ])->post($request_url);

            $responseArray = $response->json();
            // Log::channel('template_builder')->info('Response: ' . print_r($responseArray, true));

            if (isset($responseArray['status']) && $responseArray['status'] == true) {
                Log::channel('template_builder')->info('SUCCESS: ' . $responseArray['message'] . ' Ebay Item ID -> ' . $item_id);
                if ($itemSource) {
                    $itemSource->template_applied = 1;
                    $itemSource->save();
                }
            } else {
                // TemplateJob::dispatch($this->ebay_item_ids);
                Log::channel('template_builder')->error('ERROR: Message -> ' . $responseArray['message']);
            }
        }
    }


    public function update_template($item_id)
    {
        $request_url = $this->url . $this->update_endpoint . $item_id;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer 1|RB78qoDgH4Vq8s9LqFGsqeo6Ninb79eLWNJsJSBu907ac95c',
            'Accept' => 'application/json'
        ])->post($request_url);

        $responseArray = $response->json();
        // Log::channel('template_builder')->info('Response: ' . print_r($responseArray, true));

        if (isset($responseArray['status']) && $responseArray['status'] == true) {
            Log::channel('template_builder')->info('✅✅✅: ' . $responseArray['message'] . ' Ebay Item ID -> ' . $item_id);
        } else {
            // TemplateJob::dispatch($this->ebay_item_ids);
            Log::channel('template_builder')->error('❌❌❌: Message -> ' . $responseArray['message'] . ' in file ' .  $responseArray['file'] .
            ' on line ' . $responseArray['line']);
        }
    }
}
