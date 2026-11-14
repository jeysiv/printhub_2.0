<?php
namespace PrintHub;

use Exception;

/**
 * ValidationException  -  our OWN exception type.
 * We throw it when the user did something wrong (bad input, email already used...).
 * It carries a LIST of messages that the page can show to the user.
 *
 * OOP CONCEPT: INHERITANCE  ("extends Exception" = it IS an Exception, plus extra features)
 */
class ValidationException extends Exception
{
    private array $errors;

    public function __construct(array $errors)
    {
        parent::__construct(implode(' ', $errors)); // build the normal Exception message
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
