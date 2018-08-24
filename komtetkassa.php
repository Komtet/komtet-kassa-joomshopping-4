<?php

JLoader::register('komtetHelper', JPATH_PLUGINS.'/jshoppingcheckout/komtetkassa/helpers/komtethelper.php');

class plgJshoppingcheckoutKomtetkassa extends JPlugin
{

    protected $autoloadLanguage = true;

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

    public function fiscalize($order, $params)
    {
        komtetHelper::fiscalize($order, $params);
        return;
    }

    public function onAfterDisplayCheckoutFinish(&$text, &$order, &$pm_method)
    {
        if($this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params);
        }
        return true;
    }

    public function onKomtetKassaFiscalize(&$order)
    {
        if ( !in_array($order->order_status, array(0,3,4)) && $this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params);
        }
        return true;
    }

    public function onStep7BefereNotify(&$order, &$jshopCheckoutBuy, &$pmconfigs)
    {
        if ( !in_array($order->order_status, array(0,3,4)) && $this->isShouldFiscalize($order->payment_method_id))
        {
            $this->fiscalize($order, $this->params);
        }
        return true;
    }

}