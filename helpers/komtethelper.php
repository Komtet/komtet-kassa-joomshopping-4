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

    private $db;
    private $query;


    function __construct() {
        $this->db = JFactory::getDbo();
    }

    public function is_order_already_fiscalized($order_id, $order_number){
        $this->query = $this->db->getQuery(true);
        $this->query->select('*');
        $this->query->from($this->db->quoteName('#__jshopping_order_fiscalization_status', 'status'));
        $this->query->where($this->db->quoteName('status.order_id')." = ".$this->db->quote($order_id));
        $this->query->where($this->db->quoteName('status.order_number')." = ".$this->db->quote($order_number));
        $this->db->setQuery($this->query);
        $before_inserted = $this->db->loadObjectList();

        foreach( $before_inserted as $bi ) {
            if ($bi->status == 'done') {
                return True;
            }
        }
        return False;
    }

    public function get_inserted_order_fisc_status($order, $eventName, $timeNow) {
        $this->query = $this->db->getQuery(true);
        $this->query->select('*');
        $this->query->from($this->db->quoteName('#__jshopping_order_fiscalization_status', 'status'));
        $this->query->where($this->db->quoteName('status.order_id')." = ".$this->db->quote($order->order_id));
        $this->query->where($this->db->quoteName('status.event')." = ".$this->db->quote($eventName));
        $this->query->where($this->db->quoteName('status.datetime')." = ".$this->db->quote($timeNow));
        $this->db->setQuery($this->query);
        $now_inserted = $this->db->loadObject();
        return $now_inserted;
    }

    public function save_order_fisc_status($order, $eventName, $timeNow) {
        $order_fics_status = new stdClass();
        $order_fics_status->order_id = $order->order_id;
        $order_fics_status->order_number = $order->order_number;
        $order_fics_status->status='pending';
        $order_fics_status->event=$eventName;
        $order_fics_status->datetime=$timeNow;
        $this->db->insertObject('#__jshopping_order_fiscalization_status', $order_fics_status);

        return $this->get_inserted_order_fisc_status($order, $eventName, $timeNow);
    }

    public function update_order_fisc_status($order) {
        $order_fics_status = new stdClass();
        $order_fics_status->id = $order->id;
        $order_fics_status->status='done';
        $order_fics_status->datetime=date(DATE_ATOM, time());
        $db = JFactory::getDbo();
        $result = $this->db->updateObject('#__jshopping_order_fiscalization_status', $order_fics_status, 'id');
    }

    public function get_order_positions($order){
        $this->query = $this->db->getQuery(true);
        $this->query->select('*');
        $this->query->from($this->db->quoteName('#__jshopping_order_item', 'position'));
        $this->query->join('INNER', $this->db->quoteName('#__jshopping_orders', 'order') . ' ON (' . $this->db->quoteName('order.order_id') . ' = ' . $this->db->quoteName('position.order_id') . ')');
        $this->query->where($this->db->quoteName('position.order_id')." = ".$this->db->quote($order->order_id));
        $this->db->setQuery($this->query);
        $positions = $this->db->loadObjectList();
        return $positions;
    }

    public function fiscalize($order, $params, $eventName)
    {

        $session = JFactory::getSession();

        $komtet_fisc_now_orders = $session->get('komtet_fisc_now_orders', array());

        if (in_array($order->id, $komtet_fisc_now_orders)) return;

        $komtet_fisc_now_orders[] = $order->id;
        $session->set( 'komtet_fisc_now_orders', $komtet_fisc_now_orders);

        $component_path = JPATH_PLUGINS.'/system/komtetkassa';

        include_once $component_path.'/helpers/kassa/QueueManager.php';
        include_once $component_path.'/helpers/kassa/Position.php';
        include_once $component_path.'/helpers/kassa/Check.php';
        include_once $component_path.'/helpers/kassa/Client.php';
        include_once $component_path.'/helpers/kassa/Vat.php';
        include_once $component_path.'/helpers/kassa/Payment.php';
        include_once $component_path.'/helpers/kassa/Exception/SdkException.php';

        if ($this->is_order_already_fiscalized($order->order_id, $order->order_number)) {

            if (($key = array_search($order->id, $komtet_fisc_now_orders)) !== false) {
                unset($komtet_fisc_now_orders[$key]);
                $session->set( 'komtet_fisc_now_orders', $komtet_fisc_now_orders);
            }

            return;
        }

        $timeNow = date(DATE_ATOM, time());
        $now_inserted = $this->save_order_fisc_status($order, $eventName, $timeNow);

        $positions = $this->get_order_positions($order);

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

        $now_inserted = $this->get_inserted_order_fisc_status($now_inserted, $eventName, $timeNow);

        if($now_inserted->status != 'error') {
            $this->update_order_fisc_status($now_inserted);
        }

        if (($key = array_search($order->id, $komtet_fisc_now_orders)) !== false) {
            unset($komtet_fisc_now_orders[$key]);
            $session->set( 'komtet_fisc_now_orders', $komtet_fisc_now_orders);
        }
    }
}
