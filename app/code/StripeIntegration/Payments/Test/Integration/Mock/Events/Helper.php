<?php

namespace StripeIntegration\Payments\Test\Integration\Mock\Events;

class Helper
{
    protected $eventID;

    public function __construct()
    {
        $this->eventID = time();
    }

    public function getNewId()
    {
        return 'evt_xxx_' . $this->eventID++;
    }
}
