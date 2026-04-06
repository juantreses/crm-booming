<?php

namespace Espo\Custom\Enums;

enum LeadEventType: string
{
    // Call events
    case CALLED = 'called';
    case CALL_AGAIN = 'call_again';
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case NOT_INTERESTED = 'not_interested';
    
    // Message events
    case MESSAGE_TO_BE_SENT = 'message_to_be_sent';
    case MESSAGE_SENT = 'message_sent';
    case LOG_MESSAGE_OUTCOME = 'log_message_outcome';
    
    // Intro events
    case BOOK_INTRO = 'book_intro';
    case ATTEND_INTRO = 'attend_intro';
    case INTRO_NO_SHOW = 'intro_no_show';
    case INTRO_CANCELLED = 'intro_cancelled';
    
    // Kickstart events
    case LOG_KICKSTART = 'log_kickstart';
    case LOG_KICKSTART_FOLLOW_UP = 'log_kickstart_follow_up';
    case KICKSTART_NO_SHOW = 'kickstart_no_show';
    case KICKSTART_BOOKED = 'kickstart_booked';
    case KICKSTART_CANCELLED = 'kickstart_cancelled';
    case STILL_THINKING = 'still_thinking';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';
    
    // Assignment
    case ASSIGNED = 'assigned';
    
    // Outcome events
    case BECAME_CLIENT = 'became_client';
    case BECAME_COACH = 'became_coach';
    case NOT_CONVERTED = 'not_converted';
}