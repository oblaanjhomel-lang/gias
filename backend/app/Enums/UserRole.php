<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Coordinator = 'coordinator';
    case Partner = 'partner';
    case Student = 'student';
}
