<?php

namespace DavidRoberto\SyliusExtraApiPlugin\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Sylius\Component\Core\Model\ShopUserInterface;

class JWTCreatedListener
{
    public function __construct(
        $customerRepository
    ){
        $this->customerRepository=$customerRepository;
    }
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        
        $payload = $event->getData();
        if ($event->getUser() instanceof ShopUserInterface) {
            $payload['id'] = $event->getUser()->getCustomer()->getId();
        }
        else{
            $customer = $this->customerRepository->findOneBy(['email'=>$event->getUser()->getUsername()]);
            if($customer)
                $payload['id'] = $customer->getId();
            $event->setData($payload);
            return;
        }
        $event->setData($payload);
    }

}
