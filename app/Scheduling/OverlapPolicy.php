<?php

namespace App\Scheduling;

/**
 * Which instant to use when the wall time happens twice, because the clocks go back over it (e.g. 01:30 on the
 * night clocks go from 02:00 back to 01:00). Exactly one is used, never both.
 */
enum OverlapPolicy: string
{
    /** The first 01:30, still on summer time. */
    case EARLIER = 'earlier';

    /** The second 01:30, after the clocks went back. */
    case LATER = 'later';
}
