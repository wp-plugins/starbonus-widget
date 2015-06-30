<?php

namespace Starbonus\Api\Type;

/**
 * Class TypeInterface
 *
 * @author Adam Kuśmierz <adam@kusmierz.be>
 * @package Starbonus\Api\Type
 */
interface TypeInterface
{
    /**
     * Returns available values for this type
     *
     * @return array
     */
    public static function getValues();
}
