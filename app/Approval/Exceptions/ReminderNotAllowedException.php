<?php

namespace App\Approval\Exceptions;

use RuntimeException;

/**
 * A stuck-document reminder that cannot be sent right now. The message is
 * plain words meant for the admin, shown as-is in the error toast.
 */
class ReminderNotAllowedException extends RuntimeException {}
