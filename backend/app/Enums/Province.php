<?php

namespace App\Enums;

/**
 * Provinces (and Davao City) of the Davao Region (Region XI).
 */
enum Province: string
{
    case DavaoDelNorte = 'Davao del Norte';
    case DavaoCity = 'Davao City';
    case DavaoDeOro = 'Davao de Oro';
    case DavaoDelSur = 'Davao del Sur';
    case DavaoOriental = 'Davao Oriental';
    case DavaoOccidental = 'Davao Occidental';
}
