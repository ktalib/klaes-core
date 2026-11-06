<?php

namespace App\Services\Survey;

/**
 * Approximate Kano LGA centers for placing the portal beacon-request pin.
 * Derived from geoBoundaries GRID3 data via World GeoJSON (CC BY 4.0); not cadastral coordinates.
 */
class KanoLgaMapCenters
{
    /** @var array<string, array{0: float, 1: float}> */
    public const CENTERS = [
        'Ajingi' => [11.978099, 9.060478],
        'Albasu' => [11.644033, 9.109214],
        'Bagwai' => [12.103642, 8.132461],
        'Bebeji' => [11.534894, 8.310320],
        'Bichi' => [12.279867, 8.266525],
        'Bunkure' => [11.676358, 8.586561],
        'Dala' => [12.022556, 8.494650],
        'Dambatta' => [12.430065, 8.620624],
        'Dawakin Kudu' => [11.819735, 8.659999],
        'Dawakin Tofa' => [12.149355, 8.391258],
        'Doguwa' => [10.836311, 8.664849],
        'Fagge' => [12.040574, 8.517272],
        'Gabasawa' => [12.167415, 8.843321],
        'Garko' => [11.583570, 8.841083],
        'Garun Mallam' => [11.666805, 8.402268],
        'Gaya' => [11.815958, 9.030666],
        'Gezawa' => [12.051229, 8.720408],
        'Gwale' => [11.978802, 8.481559],
        'Gwarzo' => [11.901379, 7.949169],
        'Kabo' => [11.887911, 8.166893],
        'Kano Municipal' => [11.981799, 8.519055],
        'Karaye' => [11.761355, 7.996359],
        'Kibiya' => [11.497754, 8.677703],
        'Kiru' => [11.581607, 8.136523],
        'Kumbotso' => [11.920859, 8.515683],
        'Kunchi' => [12.452503, 8.264953],
        'Kura' => [11.787735, 8.486836],
        'Madobi' => [11.812291, 8.357375],
        'Makoda' => [12.420681, 8.483466],
        'Minjibir' => [12.195561, 8.613454],
        'Nasarawa' => [12.005593, 8.573837],
        'Rano' => [11.492970, 8.531867],
        'Rimin Gado' => [11.936536, 8.275460],
        'Rogo' => [11.516059, 7.838804],
        'Shanono' => [12.074997, 7.963196],
        'Sumaila' => [11.288668, 8.880585],
        'Takai' => [11.486200, 9.161043],
        'Tarauni' => [11.960525, 8.556559],
        'Tofa' => [12.017765, 8.326874],
        'Tsanyawa' => [12.281540, 8.010806],
        'Tudun Wada' => [11.294292, 8.548241],
        'Ungogo' => [12.061411, 8.507910],
        'Warawa' => [11.908334, 8.771415],
        'Wudil' => [11.763813, 8.874300],
    ];
}
