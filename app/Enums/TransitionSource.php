<?php

namespace App\Enums;

/**
 * Server-owned provenance for an audit transition.
 *
 * Clients never submit this value. The Laravel entry point that invokes the
 * approval engine supplies it, so a mobile client cannot disguise an action
 * as web-originated (or vice versa).
 */
enum TransitionSource: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case System = 'system';
}
