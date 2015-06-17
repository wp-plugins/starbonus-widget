<?php

namespace Starbonus\Api\Type;


/**
 * Class AbstractType
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Type
 */
abstract class AbstractType implements TypeInterface
{

    /**
     * @var string
     */
    protected static $name;

    /**
     * @var array
     */
    protected static $values = array();

    /**
     * Returns available values for this type
     *
     * @return array
     */
    public static function getValues()
    {
        return static::$values;
    }
}
