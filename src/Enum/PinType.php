<?php

namespace App\Enum;

enum PinType: int
{
    case PinToCommunity = 1;
    case UnpinFromCommunity = 2;
    case PinToInstance = 3;
    case UnpinFromInstance = 4;
}
