<?php

namespace DarkGhostHunter\Laraguard\Exceptions;

use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use Illuminate\Validation\ValidationException;

class InvalidCodeException extends ValidationException
{

}
