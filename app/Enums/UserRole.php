<?php

namespace App\Enums;

/** @typescript */
enum UserRole: string
{
    case USER = 'user';
    case ADMIN = 'admin';
    case OWNER = 'owner';
}
