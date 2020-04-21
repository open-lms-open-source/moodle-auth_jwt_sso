<?php

namespace MiladRahimi\Jwt\Validator\Rules\Optional;

use MiladRahimi\Jwt\Validator\Rule;

/**
 * Class LessThanOrEqualTo
 *
 * @package MiladRahimi\Jwt\Validator\Rules\Optional
 */
class LessThanOrEqualTo implements Rule
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
        return $exists == false || $value <= $this->number;
    }
}
