<?php

declare(strict_types=1);


namespace DavidRoberto\SyliusExtraApiPlugin\Controller\Api;

use Sylius\Bundle\ApiBundle\Controller;

use App\Command\Cart\RemoveItemFromCart;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;



final class DeleteOrderItemAction
{
    /** @var MessageBusInterface */
    private $commandBus;

    public function __construct(MessageBusInterface $commandBus, OrderRepositoryInterface $orderRepository)
    {
        $this->commandBus = $commandBus;
        $this->orderRepository = $orderRepository;
    }

    public function __invoke(Request $request)
    {
        $command = new RemoveItemFromCart(
            $request->attributes->get('tokenValue'),
            $request->attributes->get('itemId'),
            $request->attributes->get('boxName'),
        );
        $this->commandBus->dispatch($command);
        return $this->orderRepository->findCartByTokenValue($request->attributes->get('tokenValue'));
    }
}
