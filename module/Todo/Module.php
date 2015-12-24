<?php
namespace Todo;

use Parse\ParseClient;
use Zend\Console\Adapter\AdapterInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Mvc\MvcEvent;

class Module implements ConsoleBannerProviderInterface, ConsoleUsageProviderInterface
{
    public function onBootstrap(MvcEvent $e)
    {
        //parse uses a global client, and needs the session to be started
        $config = $e->getApplication()->getServiceManager()->get('config');
        session_start();
        ParseClient::initialize($config['parse']['app_id'], $config['parse']['rest_key'], $config['parse']['master_key']);
    }

    /**
     * @param AdapterInterface $console
     * @return string|null
     */
    public function getConsoleBanner(AdapterInterface $console)
    {
        return 'Example ToDo list application to show adding 2FA to an exsisting web application. See: https://github.com/Nexmo/ToDo-Web';
    }

    /**
     * @param AdapterInterface $console
     * @return array|string|null
     */
    public function getConsoleUsage(AdapterInterface $console)
    {
        return [
            'setup config' => 'Add Parse and Nexmo credentials to config file',
            'setup parse'  => 'Create needed Parse schema',
        ];
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