<?php


namespace DavidRoberto\SyliusExtraApiPlugin\Controller\Api;


use DavidRoberto\SyliusExtraApiPlugin\Entity\Order\Order;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use \Stripe\Checkout\Session;
use \Stripe\Stripe;
use \Stripe\PaymentIntent;

class StripeCheckoutController
{
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        RequestStack $requestStack,
        OrderRepositoryInterface $orderRepository,
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager
    ) {
        $this->requestStack = $requestStack;
        $this->orderRepository = $orderRepository;
        $this->params = $params;
        $this->entityManager = $entityManager;
    }

    public function __invoke() {
        $request = $this->requestStack->getCurrentRequest();
        $orderToken = $request->get('tokenValue');

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        header('Content-Type: application/json');

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue($orderToken);
        
        $intent = PaymentIntent::create([
        'amount' => $order->getTotal(),
        'currency' => 'usd',
        'setup_future_usage' => 'off_session',
        ]);
        //$this->persistPaymentIntentId($order, $intent['client_secret']);
        return ['id' => $intent['id'], 'client_secret'=>$intent['client_secret'], 'status'=>$intent['status']];
    }

    public function createStripeItems(OrderInterface $order): array
    {
        $items[] = [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $order->getTotal(),
                'product_data' => [
                    'name' => 'Commande Cloralys Bijoux'
                ]
            ],
            'quantity' => 1,
        ];

        return $items;

    }


//    /**
//     * @param $order
//     * @return array
//     */
//    private function getOrderItems($order): array
//    {
//        $items = [];
//
//        foreach ($order->getItems() as $item) {
//            $items[] = [
//                'price_data' => [
//                    'currency' => 'eur',
//                    'unit_amount' => $item->getUnitPrice(),
//                    'product_data' => [
//                        'name' => $item->getProductName()
//                    ]
//                ],
//                'quantity' => $item->getQuantity(),
//            ];
//        }
//        return $items;
//    }

    /**
     * @param OrderInterface $order
     * @param Session $checkoutSession
     */
    private function persistPaymentIntentId(OrderInterface $order, Session $checkoutSession): void
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $payment->setDetails(['id' => $checkoutSession->payment_intent]);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    /**
     * @param OrderInterface $order
     */
    private function completeOrder(OrderInterface $order): void
    {
        $order->setState(Order::STATE_NEW);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }
}
