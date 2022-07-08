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

class StripeCheckoutSuccessHookController
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
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $endpoint_secret = $_ENV['STRIPE_SUCCESS_HOOK_ENDPOINT_SECRET'];
        
        $payload = @file_get_contents('php://input');
        $event = null;
        if ($endpoint_secret) {
            // Only verify the event if there is an endpoint secret defined
            // Otherwise use the basic decoded event
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            try {
              $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
              );
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
              // Invalid signature
              echo '⚠️  Webhook error while validating signature.';
              http_response_code(400);
              exit();
            }
        }
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->setPaymentState($event->data->object, 'succeeded');
            break;
            case 'payment_intent.processing':
                $this->setPaymentState($event->data->object, 'processing');
            break;
            case 'payment_intent.payment_failed':
                $this->setPaymentState($event->data->object, 'payment_failed');
            break;

            default:
        }
        error_log('Received unknown event type');
        return http_response_code(400);
    }
    private function setPaymentState($hook_request, $payment_intent_state): void
    {
        if($hook_request->metadata->orderToken)
        {
            $orderToken = $hook_request->metadata->orderToken;
            $order = $this->orderRepository->findOneByTokenValue($orderToken);
            if($order){
                $payment = $order->getLastPayment();
                if($payment_intent_state=='succeeded'){
                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                    if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
                        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
                        if ($stateMachine->can(OrderPaymentTransitions::TRANSITION_PAY)) {
                            $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);
                        }
                    }
                }
                else if($payment_intent_state=='processing'){
                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                    if ($stateMachine->can(PaymentTransitions::TRANSITION_PROCESS)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
                    }
                }
                else if($payment_intent_state=='payment_failed'){
                    $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
                    if ($stateMachine->can(PaymentTransitions::TRANSITION_FAIL)) {
                        $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
                    }
                }
                $this->entityManager->flush();
                http_response_code(200);
                exit();
            }
        }
    }
}
