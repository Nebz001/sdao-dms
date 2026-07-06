<?php

namespace App\Approval\Exceptions;

use RuntimeException;

/**
 * Thrown when the same user attempts to approve the same step twice.
 */
class DuplicateApprovalException extends RuntimeException {}
