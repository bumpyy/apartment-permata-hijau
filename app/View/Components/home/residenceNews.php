<?php

namespace App\View\Components\home;

use Illuminate\View\Component;
use Illuminate\View\View;

class residenceNews extends Component
{
    public $residences_news = [
        ['title' => 'News 1', 'image' => 'https://picsum.photos/seed/3/320/240', 'date' => '2023-01-01'],
        ['title' => 'News 2', 'image' => 'https://picsum.photos/seed/4/320/240', 'date' => '2023-01-02'],
        ['title' => 'News 3', 'image' => 'https://picsum.photos/seed/5/320/240', 'date' => '2023-01-03'],
        ['title' => 'News 4', 'image' => 'https://picsum.photos/seed/6/320/240', 'date' => '2023-01-04'],
        ['title' => 'News 5', 'image' => 'https://picsum.photos/seed/7/320/240', 'date' => '2023-01-05'],
        ['title' => 'News 6', 'image' => 'https://picsum.photos/seed/8/320/240', 'date' => '2023-01-06'],
        ['title' => 'News 7', 'image' => 'https://picsum.photos/seed/9/320/240', 'date' => '2023-01-07'],
    ];

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.home.residence-news', [
            'data' => $this->residences_news,
        ]);
    }
}
