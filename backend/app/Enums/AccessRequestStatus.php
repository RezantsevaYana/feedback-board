<?php

namespace App\Enums;

/** Where an access request to a private board stands (access_requests.status). */
enum AccessRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}