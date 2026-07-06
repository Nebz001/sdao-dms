<?php

namespace App\Approval\Exceptions;

use RuntimeException;

/**
 * Thrown when a user attempts to act on a step they are not resolved for.
 */
class UnauthorizedApproverException extends RuntimeException {}
