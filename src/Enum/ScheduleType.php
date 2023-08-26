<?php

namespace App\Enum;

enum ScheduleType: int
{
    case Day = 0;
    case Week = 1;
    case Month = 2;
}
