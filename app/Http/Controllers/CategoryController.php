<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(){

        return view('category.view');
    }

    public function mapping(Request $request){

        dd($request->all());

    }


}
