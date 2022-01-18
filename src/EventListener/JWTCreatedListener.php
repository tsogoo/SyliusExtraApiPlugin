<?php

namespace DavidRoberto\SyliusExtraApiPlugin\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Sylius\Component\Core\Model\ShopUserInterface;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        
        $payload = $event->getData();
        if ($event->getUser() instanceof ShopUserInterface) {
            $payload['id'] = $event->getUser()->getCustomer()->getId();
        }
        else{
            return;
        }
        $event->setData($payload);
    }

}
