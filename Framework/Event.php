<?php
namespace Framework;

use League\Event\AbstractEvent;

class Event extends AbstractEvent
{
    protected $name = '';

    public function getName()
    {
        return $this->name;
    }
}
