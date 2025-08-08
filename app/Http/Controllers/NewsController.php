<?php

namespace App\Http\Controllers;

use App\Models\News;

class NewsController extends Controller
{
    public function index()
    {
        $news = News::all();

        return view('news.index', compact('news'));
    }

    public function show($slug)
    {

        return view('news.show', compact('newsItem'));
    }
}
