<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function index ($log_name){

        $title = strtoupper(str_replace('_', ' ', $log_name)) .':';

        $logFile = storage_path("logs/$log_name.log");
        $logs = [];
        if (File::exists($logFile)) {
            $logs = File::lines($logFile)->reverse()->toArray(); 
        }else{
            return redirect()->back();
        }
        return view('logs.index', ['logs' => $logs, 'log_name'=>$title]);
    }
    
    public function sync_products()
    {
        $log_name = 'Sync Products Logs';
        $logFile = storage_path('logs/sync_products.log');
        $logs = [];

        if (File::exists($logFile)) {
            $logs = File::lines($logFile)->reverse()->toArray(); 
        }

        return view('logs.index', ['logs' => $logs, 'log_name'=>$log_name]);
    }
    public function shopify_webhook()
    {
        $log_name = 'Shopify Webhook Logs';
        $logFile = storage_path('logs/shopify_webhook.log');
        $logs = [];

        if (File::exists($logFile)) {
            $logs = File::lines($logFile)->reverse()->toArray(); 
        }

       return view('logs.index', ['logs' => $logs, 'log_name'=>$log_name]);
    }
    public function ebay_webhook()
    {
        $log_name = 'Ebay Webhook Logs';
        $logFile = storage_path('logs/ebay_webhook.log');
        $logs = [];

        if (File::exists($logFile)) {
            $logs = File::lines($logFile)->reverse()->toArray(); 
        }

       return view('logs.index', ['logs' => $logs, 'log_name'=>$log_name]);
    }
}
