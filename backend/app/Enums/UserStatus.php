<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    /** Registration returned by a reviewer (see users.review_note). */
    case Rejected = 'rejected';
}
