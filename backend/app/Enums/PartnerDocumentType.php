<?php

namespace App\Enums;

enum PartnerDocumentType: string
{
    case SecDti = 'sec_dti';
    case BusinessPermit = 'business_permit';
    case MoaDraft = 'moa_draft';
    case Other = 'other';
}
