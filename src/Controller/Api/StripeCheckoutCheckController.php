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
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use SM\Factory\FactoryInterface;
use \Stripe\Checkout\Session;
use \Stripe\Stripe;
use \Stripe\PaymentIntent;
use Symfony\Component\HttpFoundation\Response;

class StripeCheckoutCheckController
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
        FactoryInterface $stateMachineFactory,
        EntityManagerInterface $entityManager
    ) {
        $this->requestStack = $requestStack;
        $this->orderRepository = $orderRepository;
        $this->params = $params;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->entityManager = $entityManager;
    }

    public function __invoke() {
        $request = $this->requestStack->getCurrentRequest();
        $orderToken = $request->get('tokenValue');
        $order = $this->orderRepository->findOneByTokenValue($orderToken);
        
        
        $payment = $order->getLastPayment();
        
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        // $response = PaymentIntent::confirm(
        //     $payment->getDetails()['id'],
        //     ['payment_method' => 'card']
        // );
        //if($response->status == 'succeeded'){
        if('succeeded'){
            $this->handlePaymentSuccess($order, $payment, null);
            return new Response(Response::HTTP_OK);
        }
        else{
            return new Response(Response::HTTP_BAD_REQUEST);
        }
    }

    private function handlePaymentSuccess($order, $payment, $payment_response): void
    {
        $this->completePayment($order, $payment, $payment_response);
    }

    /**
     * @param $session
     * @throws \SM\SMException
     */
    private function completePayment($order, $payment, $payment_response): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
        $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);

        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

//        $this->emailSender->send(
//            Emails::ORDER_CONFIRMATION_RESENT,
//            [$order->getCustomer()->getEmail()],
//            [
//                'order' => $order,
//                'channel' => $order->getChannel(),
//                'localeCode' => $order->getLocaleCode(),
//            ]
//        );
        $this->entityManager->flush();
    }
}
