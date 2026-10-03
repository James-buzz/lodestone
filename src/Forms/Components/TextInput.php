<?php

namespace Lodestone\Forms\Components;

/**
 * A single-line input: TextInput::make('email')->email()->required()
 */
class TextInput extends Field
{
    protected string $inputType = 'text';

    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected int|float|null $minValue = null;

    protected int|float|null $maxValue = null;

    protected int|float|null $step = null;

    protected bool $integer = false;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    /**
     * Expect an email address.
     */
    public function email(): static
    {
        $this->inputType = 'email';

        return $this;
    }

    /**
     * Expect a URL.
     */
    public function url(): static
    {
        $this->inputType = 'url';

        return $this;
    }

    /**
     * Expect a phone number.
     */
    public function tel(): static
    {
        $this->inputType = 'tel';

        return $this;
    }

    /**
     * Expect a password. It always starts empty, so on an edit form an untouched field arrives as null: skip it in the callback.
     */
    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    /**
     * Expect a number.
     */
    public function numeric(): static
    {
        $this->inputType = 'number';

        return $this;
    }

    /**
     * Expect a whole number.
     */
    public function integer(): static
    {
        $this->inputType = 'number';
        $this->integer = true;
        $this->step ??= 1;

        return $this;
    }

    /**
     * Set the minimum length.
     */
    public function minLength(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /**
     * Set the maximum length.
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Set the minimum value, for numbers.
     */
    public function minValue(int|float $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    /**
     * Set the maximum value, for numbers.
     */
    public function maxValue(int|float $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    /**
     * Set the step between values, for numbers.
     */
    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    /**
     * Set the text shown inside the input before the value, e.g. '£' or 'https://'.
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Set the text shown inside the input after the value, e.g. 'kg' or '%'.
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Normalize the value for the control. A password never travels to the browser, so it always starts empty.
     */
    public function normalize(mixed $value): mixed
    {
        return $this->inputType === 'password' ? null : parent::normalize($value);
    }

    /**
     * Get the control type.
     */
    protected function type(): string
    {
        return $this->inputType;
    }

    /**
     * Get the validation rules the control implies.
     */
    protected function typeRules(): array
    {
        $number = $this->inputType === 'number';

        return array_values(array_filter([
            match ($this->inputType) {
                'email' => 'email',
                'url' => 'url',
                'number' => $this->integer ? 'integer' : 'numeric',
                default => 'string',
            },
            ! $number && $this->minLength !== null ? "min:{$this->minLength}" : null,
            ! $number && $this->maxLength !== null ? "max:{$this->maxLength}" : null,
            $number && $this->minValue !== null ? "min:{$this->minValue}" : null,
            $number && $this->maxValue !== null ? "max:{$this->maxValue}" : null,
        ]));
    }

    /**
     * Get the settings the control needs.
     */
    protected function extra(): array
    {
        return [
            'max' => $this->inputType === 'number' ? $this->maxValue : $this->maxLength,
            'min' => $this->inputType === 'number' ? $this->minValue : $this->minLength,
            'step' => $this->step,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ];
    }
}
