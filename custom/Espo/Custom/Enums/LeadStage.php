<?php

namespace Espo\Custom\Enums;

enum LeadStage: string
{
    case TO_CALL = 'to_call';
    case FOLLOW_UP = 'follow_up';
    case MESSAGE_TO_BE_SENT = 'message_to_be_sent';
    case MESSAGE_SENT = 'message_sent';
    case INTRO_SCHEDULED = 'intro_scheduled';
    case INTRO_ATTENDED = 'intro_attended';
    case BOOK_KS = 'book_ks';
    case KS_PLANNED = 'ks_planned';
    case KS_DOUBT = 'ks_doubt';
    case BECAME_CLIENT = 'became_client';
}