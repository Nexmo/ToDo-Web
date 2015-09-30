<?php
namespace Todo;

use Parse\ParseClient;
use Zend\Mvc\MvcEvent;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        //parse uses a global client, and needs the session to be started
        $config = $e->getApplication()->getServiceManager()->get('config');
        session_start();
        ParseClient::initialize($config['parse']['app_id'], $config['parse']['rest_key'], $config['parse']['master_key']);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}