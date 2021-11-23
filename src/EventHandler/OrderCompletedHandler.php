<?php


namespace DavidRoberto\SyliusExtraApiPlugin\EventHandler;

use Sylius\Bundle\ApiBundle\Event\OrderCompleted;
use Symfony\Component\Messenger\MessageBusInterface;

/** @experimental */
final class OrderCompletedHandler
{
    /** @var MessageBusInterface */
    private $commandBus;

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    public function __invoke(OrderCompleted $orderCompleted): void
    {
        $this->commandBus->dispatch(new SendOrderConfirmation($orderCompleted->orderToken()));
    }
}

