<?php

namespace App\Scheduling;

/**
 * What to do when the wall time does not exist, because the clocks jump forward over it (e.g. 02:30 on the
 * night clocks go from 02:00 to 03:00).
 */
enum GapPolicy: string
{
    /** Move forward by the length of the gap: 02:30 becomes 03:30. */
    case SHIFT_FORWARD = 'shift_forward';

    /** Use the first minute that exists after the gap: 02:30 becomes 03:00. */
    case NEXT_VALID_MINUTE = 'next_valid_minute';
}
