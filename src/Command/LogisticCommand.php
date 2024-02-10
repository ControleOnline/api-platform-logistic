<?php

namespace ControleOnline\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;
use ControleOnline\Entity\ReceiveInvoice;
use ControleOnline\Entity\SalesOrderInvoice;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\OrderLogistic;
use ControleOnline\Service\DatabaseSwitchService;

class LogisticCommand extends Command
{
  protected static $defaultName = 'app:logistic:run';

  protected $em;

  protected $ma;

  protected $errors = [];

  /**
   * Entity manager
   *
   * @var DatabaseSwitchService
   */
  private $databaseSwitchService;

  /**
   * Config repository
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  public function __construct(EntityManagerInterface $entityManager, DatabaseSwitchService $databaseSwitchService)
  {
    $this->em     = $entityManager;


    $this->errors = [];
    $this->databaseSwitchService = $databaseSwitchService;

    parent::__construct();
  }

  protected function configure()
  {
    $this
      ->setDescription('Sends notifications according to order status.')
      ->setHelp('This command cares of send order notifications.');

    $this->addArgument('target', InputArgument::REQUIRED, 'Notifications target');
    $this->addArgument('limit', InputArgument::OPTIONAL, 'Limit of orders to process');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    $domains = $this->databaseSwitchService->getAllDomains();
    foreach ($domains as $domain) {
      $this->databaseSwitchService->switchDatabaseByDomain($domain);

      $this->output = $output;

      $targetName = $input->getArgument('target');
      $orderLimit = $input->getArgument('limit') ?: 100;

      $getOrders  = 'get' . str_replace('_', '', ucwords(strtolower($targetName), '_')) . 'Orders';
      if (method_exists($this, $getOrders) === false)
        throw new \Exception(sprintf('Notification target "%s" is not defined', $targetName));

      $this->output->writeln([
        '',
        '=========================================',
        sprintf('Notification target: %s', $targetName),
        '=========================================',
        sprintf('Rows to process: %d', $orderLimit),
        '',
      ]);

      // get orders

      $orders = $this->$getOrders($orderLimit);

      if (!empty($orders)) {
        foreach ($orders as $order) {


          $result = $order->notifier['send']();

          if (is_bool($result)) {
            $order->events[$result === true ? 'onSuccess' : 'onError']();
          } else {
            if ($result === null) {
              $this->output->writeln(['      Error   : send method internal error']);
            }
          }

          $this->output->writeln(['']);
        }
      } else
        $this->output->writeln('      There is no pending orders.');

      $this->output->writeln([
        '',
        '=========================================',
        'End of Order Notifier',
        '=========================================',
        '',
      ]);
    }
    return 0;
  }


  /**
   * Cria as invoices da logística
   */
  private function getCreateLogisticInvoiceOrders(int $limit = 10, int $datelimit = 20): ?array
  {

    $qry = $this->em->getRepository(OrderLogistic::class)
      ->createQueryBuilder('OL')
      ->select()
      ->where('OL.status IN(:status)')
      ->andWhere('OL.purchasing_order IS NULL')
      ->andWhere('OL.provider IS NOT NULL')
      ->setParameters(array(
        'status' => $this->em->getRepository(Status::class)->findBy(['realStatus' => 'closed', 'context' => 'logistic']),
      ))
      ->groupBy('OL.id')
      ->setMaxResults($limit)
      ->getQuery();


    $OrderLogistic = $qry->getResult();


    if (count($OrderLogistic) == 0)
      return null;
    else {
      foreach ($OrderLogistic as $logistic) {
        $order = $logistic->getOrder();
        $orders[] = (object) [
          'order'    => $order->getId(),
          'carrier'  => $order->getQuote() ? $order->getQuote()->getCarrier()->getName() : '',
          'company'  => $order->getProvider()->getName(),
          'receiver' => $order->getClient() ? $order->getClient()->getName() : null,
          'subject'  => 'Create logistic order',
          'notifier' => [
            'send' => function () use ($order) {
              try {
                return true;
              } catch (\Exception $e) {
                return false;
              }
            },
          ],
          'events'   => [
            'onError' => function () use ($order, $logistic) {
            },
            'onSuccess' => function () use ($order, $logistic) {
              try {

                $logisticOrder = clone $order;
                $this->em->detach($logisticOrder);
                $logisticOrder->resetId();
                $logisticOrder->setOrderType('purchase');
                $logisticOrder->setMainOrder($order);
                $logisticOrder->setClient($order->getProvider());
                $logisticOrder->setPayer($order->getProvider());
                $logisticOrder->setProvider($logistic->getProvider());
                $logisticOrder->setPrice($logistic->getAmountPaid());
                $logisticOrder->setParkingDate($order->getParkingDate());
                $this->em->persist($logisticOrder);
                $this->em->flush($logisticOrder);

                $logistic->setPurchasingOrder($logisticOrder);
                $this->em->persist($logistic);
                $this->em->flush($logistic);



                $invoice = new ReceiveInvoice();
                $invoice->setPrice($order->getPrice());
                $invoice->setDueDate($this->getDueDate($order->getClient()));
                $invoice->setStatus($this->em->getRepository(Status::class)->findOneBy(['status' => ['waiting payment'], 'context' => 'invoice']));
                $invoice->setNotified(0);
                $invoice->setDescription('Frete');
                $invoice->setCategory(
                  $this->em->getRepository(Category::class)->findOneBy([
                    'context'  => 'expense',
                    'name'    => 'Frete',
                    'company' => [$order->getProvider(), $order->getClient()]
                  ])
                );

                $orderInvoice = new SalesOrderInvoice();
                $orderInvoice->setInvoice($invoice);
                $orderInvoice->setOrder($logisticOrder);
                $orderInvoice->setRealPrice($logisticOrder->getPrice());

                $invoice->addOrder($orderInvoice);

                $this->em->persist($invoice);
                $this->em->flush($invoice);

                $this->em->persist($orderInvoice);
                $this->em->flush($orderInvoice);
              } catch (\Exception $e) {
                echo $e->getMessage();
              }
            },
          ],
        ];
      }
    }
    return $orders;
  }
}
