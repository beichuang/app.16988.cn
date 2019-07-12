<?php
namespace Framework;

use League\Event\AbstractListener;
use League\Event\EventInterface;

class Listener extends AbstractListener
{
    public function handle(EventInterface $event)
    {
        throw new \Exception("Please implement this method and don't call this method!");
    }
}
