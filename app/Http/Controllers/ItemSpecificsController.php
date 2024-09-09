<?php

namespace App\Http\Controllers;

use App\Models\ItemSpecific;
use Illuminate\Http\Request;
use JsonException;

class ItemSpecificsController extends Controller
{
    public function index(){
        
        $item_specifics = ItemSpecific::orderBy('created_at', 'DESC')->get(); 

        return view('item_specifics.index' , compact('item_specifics'));
    }

    public function add_aspect(Request $request ){
        $aspect_name = $request->aspect_name;

        try {
            ItemSpecific::create([
                'aspect_name' => ucwords($aspect_name)
            ]);
            return redirect()->back()->with('message', 'Aspect added successfully.');
        } catch (\Throwable $th) {
            return redirect()->back()->with('message', 'Error: ' . $th->getMessage());
        }
    }

    public function add_keywords($aspect_id){

        $aspect = ItemSpecific::find($aspect_id);
        $aspect->aspect_values = json_decode($aspect->aspect_values);
        // dd($aspect);
        return view('item_specifics.add_keywords', compact('aspect'));
    }

    public function store_keywords(Request $request , $aspect_id){
        // dd($aspect_id);
        $aspects_values = $request->input('aspects', []);
        $aspects_values = array_values($aspects_values);
        
        foreach ($aspects_values as $key => $array) {
            $aspects_values[$key]['keys'] = array_map('trim', explode(',', $array['keys']));
        }
        // dd($aspects);

        try {
            
            $aspect = ItemSpecific::find($aspect_id);
            $aspect->aspect_values = json_encode($aspects_values);
            $aspect->save();

            return redirect()->back()->with('message', 'Aspects keywords saved successfully.');
        } catch (\Throwable $th) {
            return redirect()->back()->with('message', 'Error: ' . $th->getMessage());
        }
    }
}
