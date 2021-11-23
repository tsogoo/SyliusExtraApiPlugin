<?php


namespace DavidRoberto\SyliusExtraApiPlugin\Controller\Api;


use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SM\Factory\FactoryInterface;
use Stripe\Event;
use Sylius\Bundle\CoreBundle\Mailer\Emails;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use \Stripe\Webhook;
use \Stripe\Stripe;

class StripeNotifySuccessController
{

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var PaymentRepositoryInterface
     */
    private $paymentRepository;
    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var SenderInterface
     */
    private $emailSender;

    public function __construct(
        RequestStack $requestStack,
        ParameterBagInterface $params,
        LoggerInterface $logger,
        PaymentRepositoryInterface $paymentRepository,
        FactoryInterface $stateMachineFactory,
        EntityManagerInterface $entityManager,
        SenderInterface $emailSender
    ) {
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->logger = $logger;
        $this->paymentRepository = $paymentRepository;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->entityManager = $entityManager;
        $this->emailSender = $emailSender;
    }

    public function __invoke()
    {
        $request = $this->requestStack->getCurrentRequest();
        $orderToken = $request->get('tokenValue');
        $order = $this->orderRepository->findOneByTokenValue($orderToken);
        
        
        $payment = $order->getLastPayment();
        
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $response = PaymentIntent::confirm(
            $payment->getDetails()['id'],
            ['payment_method' => 'card']
        );
        if($response->status == 'succeeded'){
            $this->handlePaymentSuccess($payment, $response);
            return new Response(Response::HTTP_OK);
        }
        else{
            return new Response(Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param Event $event
     * @throws \SM\SMException
     */
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
