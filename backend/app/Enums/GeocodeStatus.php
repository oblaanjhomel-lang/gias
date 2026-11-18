<?php

namespace App\Enums;

enum GeocodeStatus: string
{
    case Pending = 'pending';
    case Located = 'located';
    case Failed = 'failed';
    case Manual = 'manual';
}
