<?php

namespace App\Services\Hr;

/** Status string kompatibel legacy greatday / portal. */
final class WorkflowStatus
{
    public const PENDING = 'Pending';

    public const APPROVED_ATASAN = 'Approved Atasan';

    public const REJECTED_ATASAN = 'Rejected Atasan';

    public const APPROVED_HRD = 'Approved HRD';

    public const REJECTED_HRD = 'Rejected HRD';

    public const APPROVED_FINANCE = 'Approved Finance';

    public const REJECTED_FINANCE = 'Rejected Finance';
}
