<?php

JLoader::register('komtetHelper', JPATH_PLUGINS.'/system/komtetkassa/helpers/komtethelper.php');

class plgSystemKomtetkassa extends JPlugin
{

    protected $autoloadLanguage = true;

    private $order_paid_status = 6;
    private $order_complete_status = 7;

    public function isShouldFiscalize($pm_system_id)
    {
        $pm_methods_ids = explode(',', $this->params['pm_methods']);
        foreach ($pm_methods_ids as $pm_m_id)
        {
            $pm_m_id = trim($pm_m_id);
        }

        if (in_array($pm_system_id, $pm_methods_ids))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function fiscalize($order, $params, $eventName)
    {
        $komtetFiscalizer = new komtetHelper();
        $komtetFiscalizer->fiscalize($order, $params, $eventName);
        return;
    }

    public function onAfterDisplayCheckoutFinish(&$text, &$order, &$pm_method)
    {
        if($this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params, 'onAfterDisplayCheckoutFinish');
        }
        return true;
    }

    public function onKomtetKassaFiscalize(&$order)
    {
        if (in_array($order->order_status, array($this->order_paid_status,$this->order_complete_status)) &&
             $this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params, 'onKomtetKassaFiscalize');
        }
        return true;
    }

    public function onStep7BefereNotify(&$order, &$jshopCheckoutBuy, &$pmconfigs)
    {
        if (in_array($order->order_status, array($this->order_paid_status,$this->order_complete_status)) &&
            $this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params, 'onStep7BefereNotify');
        }
        return true;
    }

    public function onAfterChangeOrderStatusAdmin(&$order, &$order_status, &$status_id, &$notify, &$comments, &$include, &$view_order)
    {
        
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__jshopping_orders', 'order'));
        $query->where($db->quoteName('order.order_id')." = ".$db->quote($order));
        $db->setQuery($query);
        $_order = $db->loadObject();
        
        if (in_array($_order->order_status, array($this->order_paid_status,$this->order_complete_status)) &&
            $this->isShouldFiscalize($_order->payment_method_id))
        {
            $this->fiscalize($_order, $this->params, 'onAfterChangeOrderStatusAdmin');
        }
        return true;
    }

}