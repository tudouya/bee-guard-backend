<?php

namespace App\Enums;

enum RewardIssuanceStatus: string
{
    case PendingReview = 'pending_review';
    case Ready = 'ready';
    case Issued = 'issued';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
