<?php

namespace Espo\Custom\Enums;

enum LeadReasonLost: string
{
    case WRONG_NUMBER = 'wrong_number';
    case NOT_INTERESTED = 'not_interested';
    case NOT_CONVERTED = 'not_converted';
}