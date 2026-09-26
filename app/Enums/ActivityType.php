<?php

namespace App\Enums;

enum ActivityType: string
{
    case LOGIN_SUCCESS = 'login_success';
    case LOGIN_FAILED = 'login_failed';
    case PROFILE_UPDATED = 'profile_updated';
    case PASSWORD_CHANGED = 'password_changed';
    case TWO_FACTOR_ENABLED = 'two_factor_enabled';
    case TWO_FACTOR_DISABLED = 'two_factor_disabled';
    case AUCTION_VIEWED = 'auction_viewed';
    case HOME_VIEWED = 'home_viewed';
    case WINNERS_VIEWED = 'winners_viewed';
    case OPEN_BIDS_VIEWED = 'open_bids_viewed';
    case BID_PLACED = 'bid_placed';
    case AUCTION_WON = 'auction_won';
    case POINTS_DEPOSITED = 'points_deposited';
    case POINTS_SPENT = 'points_spent';
    case BONUS_POINTS_CLAIMED = 'bonus_points_claimed';
    case DAILY_CHECK_IN = 'daily_check_in';
    case SPIN_WHEEL = 'spin_wheel';
    case REWARDS_CLAIMED = 'rewards_claimed';
    case TICKET_CREATED = 'ticket_created';
    case TICKET_CLAIMED = 'ticket_claimed';
    case TICKET_CLOSED = 'ticket_closed';
    case TICKET_MESSAGE_ADDED = 'ticket_message_added';
    case USER_REGISTERED = 'user_registered';
    case USER_STATUS_TOGGLED = 'user_status_toggled';
    case USER_ROLE_CHANGED = 'user_role_changed';
}
