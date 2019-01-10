<?php
// запрещаем доступ извне
defined('_JEXEC') or die;

use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Payment;


class komtetHelper
{
    public function fiscalize($order, $params, $eventName)
    {
        $session = JFactory::getSession();
        if ($session->get('komtet_fisc_started', False)) return;
        $session->set( 'komtet_fisc_started', True);

        $component_path = JPATH_PLUGINS.'/system/komtetkassa';

        include_once $component_path.'/helpers/kassa/QueueManager.php';
        include_once $component_path.'/helpers/kassa/Position.php';
        include_once $component_path.'/helpers/kassa/Check.php';
        include_once $component_path.'/helpers/kassa/Client.php';
        include_once $component_path.'/helpers/kassa/Vat.php';
        include_once $component_path.'/helpers/kassa/Payment.php';
        include_once $component_path.'/helpers/kassa/Exception/SdkException.php';

        $db = JFactory::getDbo();

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__jshopping_order_fiscalization_status', 'status'));
        $query->where($db->quoteName('status.order_id')." = ".$db->quote($order->order_id));
        $query->where($db->quoteName('status.order_number')." = ".$db->quote($order->order_number));
        $db->setQuery($query);
        $before_inserted = $db->loadObjectList();

        foreach( $before_inserted as $bi ) {
            if ($bi->status == 'done') {
                $session->set( 'komtet_fisc_started', False);
                return;
            }
        }

        $timeNow = date(DATE_ATOM, time());
        $order_fics_status = new stdClass();
        $order_fics_status->order_id = $order->order_id;
        $order_fics_status->order_number = $order->order_number;
        $order_fics_status->status='pending';
        $order_fics_status->event=$eventName;
        $order_fics_status->datetime=$timeNow;
        $db->insertObject('#__jshopping_order_fiscalization_status', $order_fics_status);

        $session->set( 'komtet_fisc_started', False);

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__jshopping_order_fiscalization_status', 'status'));
        $query->where($db->quoteName('status.order_id')." = ".$db->quote($order->order_id));
        $query->where($db->quoteName('status.event')." = ".$db->quote($eventName));
        $query->where($db->quoteName('status.datetime')." = ".$db->quote($timeNow));
        $db->setQuery($query);
        $now_inserted = $db->loadObject();

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__jshopping_order_item', 'position'));
        $query->join('INNER', $db->quoteName('#__jshopping_orders', 'order') . ' ON (' . $db->quoteName('order.order_id') . ' = ' . $db->quoteName('position.order_id') . ')');
        $query->where($db->quoteName('position.order_id')." = ".$db->quote($order->order_id));
        $db->setQuery($query);
        $positions = $db->loadObjectList();

        $payment = new Payment(Payment::TYPE_CARD, floatval($positions[0]->order_total));

        $parsed_sno = null;
        switch ($params['sno']) {
            case 'osn':
                $parsed_sno = 0;
                break;
            case 'usn_dohod':
                $parsed_sno = 1;
                break;
            case 'usn_dohod_rashod':
                $parsed_sno = 2;
                break;
            case 'envd':
                $parsed_sno = 3;
                break;
            case 'esn':
                $parsed_sno = 4;
                break;
            case 'patent':
                $parsed_sno = 5;
                break;
            default:
                $parsed_sno = $params['sno'];
        }

        $check = new Check($order->order_id, $order->email, Check::INTENT_SELL, intval($parsed_sno));
        $check->setShouldPrint($params['is_print_check']);
        $check->addPayment($payment);

        if ($params['vat'] == 'zero' || !$params['vat'] ) {
            $params['vat'] = 0;
        }
        $vat = new Vat($params['vat']);

        foreach( $positions as $position )
        {
            $positionObj = new Position($position->product_name,
                                        floatval($position->product_item_price),
                                        floatval($position->product_quantity),
                                        floatval($position->product_quantity*$position->product_item_price),
                                        0,
                                        $vat);

            $check->addPosition($positionObj);
        }

        $check->applyDiscount($positions[0]->order_discount);

        if (floatval($positions[0]->order_shipping) > 0.0) {
            $shippingPosition = new Position("Доставка",
                                             floatval($position->order_shipping),
                                             1,
                                             floatval($position->order_shipping),
                                             0,
                                             $vat);
            $check->addPosition($shippingPosition);
        }

        if (floatval($positions[0]->order_package) > 0.0) {
            $packagePosition = new Position("Упаковка",
                                             floatval($position->order_package),
                                             1,
                                             floatval($position->order_package),
                                             0,
                                             $vat);
            $check->addPosition($packagePosition);
        }

        $client = new Client($params['shop_id'], $params['secret']);
        $queueManager = new QueueManager($client);

        $queueManager->registerQueue('print_que', $params['queue_id']);

        try {
            $queueManager->putCheck($check, 'print_que', $now_inserted->id);
        } catch (SdkException $e) {
            $fiscErr = $e->getMessage();
            echo $fiscErr;
        }

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__jshopping_order_fiscalization_status', 'status'));
        $query->where($db->quoteName('status.id')." = ".$db->quote($now_inserted->id));
        $db->setQuery($query);
        $now_inserted = $db->loadObject();

        if($now_inserted->status != 'error') {
            $order_fics_status = new stdClass();
            $order_fics_status->id = $now_inserted->id;
            $order_fics_status->status='done';
            $order_fics_status->datetime=date(DATE_ATOM, time());
            $result = $db->updateObject('#__jshopping_order_fiscalization_status', $order_fics_status, 'id');
        }
    }
}
