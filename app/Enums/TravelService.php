<?php

namespace App\Enums;

enum TravelService: string
{
    case Flights = 'flights';
    case Hotels = 'hotels';
    case Holidays = 'holidays';
    case Charter = 'charter';
    case Pilgrimage = 'pilgrimage';
    case Visas = 'visas';
    case Cars = 'cars';
}
