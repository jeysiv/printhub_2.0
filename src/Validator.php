<?php
namespace PrintHub;

use DateTime;

/**
 * Validator  -  checks form input on the SERVER (never trust the browser alone).
 * Each check that fails adds a message to a list. At the end we ask fails().
 *
 * Usage:
 *   $v = new Validator($_POST);
 *   $v->required('name', 'Name');
 *   if ($v->fails()) { show $v->errors() }
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** Read a field as trimmed text ('' if missing). */
    public function value(string $field): string
    {
        $raw = $this->data[$field] ?? '';
        return is_string($raw) ? trim($raw) : '';
    }

    public function required(string $field, string $label): void
    {
        if ($this->value($field) === '') {
            $this->errors[] = $label . ' is required.';
        }
    }

    public function maxLength(string $field, string $label, int $max): void
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->errors[] = $label . ' must be at most ' . $max . ' characters.';
        }
    }

    public function minLength(string $field, string $label, int $min): void
    {
        if (mb_strlen($this->value($field)) < $min) {
            $this->errors[] = $label . ' must be at least ' . $min . ' characters.';
        }
    }

    public function email(string $field, string $label): void
    {
        if (filter_var($this->value($field), FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[] = $label . ' must be a valid email address.';
        }
    }

    public function matches(string $field, string $other, string $message): void
    {
        // Passwords are NOT trimmed, so compare the raw values
        if (($this->data[$field] ?? '') !== ($this->data[$other] ?? '')) {
            $this->errors[] = $message;
        }
    }

    /** Whole number between $min and $max. */
    public function integerBetween(string $field, string $label, int $min, int $max): void
    {
        $value = filter_var($this->value($field), FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $this->errors[] = $label . ' must be a whole number from ' . $min . ' to ' . $max . '.';
        }
    }

    /** Decimal number between $min and $max. */
    public function numberBetween(string $field, string $label, float $min, float $max): void
    {
        $text = $this->value($field);
        if (!is_numeric($text) || (float)$text < $min || (float)$text > $max) {
            $this->errors[] = $label . ' must be a number from ' . $min . ' to ' . $max . '.';
        }
    }

    public function inList(string $field, string $label, array $allowed): void
    {
        if (!in_array($this->value($field), $allowed, true)) {
            $this->errors[] = $label . ' has an invalid choice.';
        }
    }

    /** Date written as YYYY-MM-DD. */
    public function date(string $field, string $label): void
    {
        $d = DateTime::createFromFormat('Y-m-d', $this->value($field));
        if (!$d || $d->format('Y-m-d') !== $this->value($field)) {
            $this->errors[] = $label . ' must be a valid date.';
        }
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
