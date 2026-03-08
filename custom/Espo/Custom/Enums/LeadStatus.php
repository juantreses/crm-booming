<?php

namespace Espo\Custom\Enums;

enum LeadStatus: string
{
    case NEW = 'New';
    case ASSIGNED = 'Assigned';
    case CONVERTED = 'Converted';
    case BECAME_CLIENT = 'became_client';
    case DEAD = 'Dead';
}