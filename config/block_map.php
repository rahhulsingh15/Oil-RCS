<?php

/*
|--------------------------------------------------------------------------
| Block map settings
|--------------------------------------------------------------------------
| 'point'    => [latitude, longitude] centre of the block, used for the marker on
|               the Block Overview map. APPROXIMATE: read off the client's base
|               maps (OALP DSF slides, Oct 2026). Replace with real values when shared.
| 'boundary' => [[lat, lng], ...] optional real outline. Empty = approximate square.
| 'image'    => base map image in public/ (from the client's slides). Shown on the
|               Block Detail page instead of the approximate outline.
*/

return [

    // Map background. Can be switched to Bhuvan (ISRO) or MapmyIndia tiles
    // if the client needs India's official boundary on every map.
    'tiles' => [
        'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        'attribution' => 'Tiles &copy; Esri',
    ],

    'image_source' => 'Base map: OIL OALP DSF slides (KGB & MBP), Oct 2026',

    'blocks' => [
        // Mahanadi onland, Odisha (slides 36-39)
        'MN-ONHP-2018/1' => ['point' => [20.20, 86.05], 'boundary' => [], 'image' => 'images/blocks/mn-onhp-2018-1.jpg'], // Jagatsinghpur
        'MN-ONHP-2018/2' => ['point' => [19.95, 85.75], 'boundary' => [], 'image' => 'images/blocks/mn-onhp-2018-2.jpg'], // Puri
        'MN-ONHP-2018/3' => ['point' => [21.05, 86.65], 'boundary' => [], 'image' => 'images/blocks/mn-onhp-2018-3.jpg'], // Bhadrak
        'MN-ONHP-2018/4' => ['point' => [21.50, 86.20], 'boundary' => [], 'image' => null],                               // relinquished, no slide
        'MN-ONHP-2018/5' => ['point' => [20.58, 86.25], 'boundary' => [], 'image' => 'images/blocks/mn-onhp-2018-5.jpg'], // Cuttack

        // OALP-IX ultra deep water, offshore Andhra Pradesh (slides 13-16, one shared base map)
        'KG-UDWHP-2023/1' => ['point' => [16.75, 84.70], 'boundary' => [], 'image' => 'images/blocks/oalp-ix-offshore.jpg'],
        'KG-UDWHP-2023/2' => ['point' => [14.85, 84.70], 'boundary' => [], 'image' => 'images/blocks/oalp-ix-offshore.jpg'],
        'MN-UDWHP-2023/2' => ['point' => [15.40, 86.20], 'boundary' => [], 'image' => 'images/blocks/oalp-ix-offshore.jpg'],
        'MN-UDWHP-2023/3' => ['point' => [16.40, 86.50], 'boundary' => [], 'image' => 'images/blocks/oalp-ix-offshore.jpg'],
    ],
];
