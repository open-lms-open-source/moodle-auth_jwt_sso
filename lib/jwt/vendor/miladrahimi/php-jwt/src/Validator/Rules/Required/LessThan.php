<?php

namespace MiladRahimi\Jwt\Validator\Rules\Required;

use MiladRahimi\Jwt\Validator\Rule;

/**
 * Class LessThan
 *
 * @package MiladRahimi\Jwt\Validator\Rules\Required
 */
class LessThan implements Rule
{
    /**
     * @var float
     */
    protected $number;

    /**
     * @param float $number
     */
    public function __construct(float $number)
    {
        $this->number = $number;
    }

    /**
     * @inheritdoc
     */
    public function check($value, bool $exists): bool
    {
        return $exists && $value < $this->number;
    }
}
