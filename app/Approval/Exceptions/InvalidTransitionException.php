<?php

namespace App\Approval\Exceptions;

use RuntimeException;

/**
 * Thrown when an engine action is called on a document in the wrong status.
 */
class InvalidTransitionException extends RuntimeException {}
