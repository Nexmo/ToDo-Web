<?php
namespace Todo;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Mvc\MvcEvent;

trait VerifyTrait
{
    /**
     * @var \Nexmo\Verify
     */
    protected $verify;

    public function onDispatch(MvcEvent $e)
    {
        //trait only valid for a instance of abstract action controller
        if(!$this instanceof AbstractActionController){
            throw new \Exception('Must extend AbstractActionController to use VerifyTrait');
        }

        //get verify
        $this->verify = $this->getServiceLocator()->get('Nexmo\Verify');
        return parent::onDispatch($e);
    }

    protected function startVerification($number, $url = null)
    {
        $response = $this->verify->verify([
            'number' =>  $number,
            'brand' => 'ToDo List'
        ]);

        if($response['status'] != 0){
            $this->flashMessenger()->addErrorMessage($response['error_text']);
        }

        $_SESSION['verify']['url'] = $url;
        $_SESSION['verify']['request'] = $response['request_id'];
    }

    protected function verifyPrompt($prompt)
    {
        $view = new ViewModel([
            'prompt' => $prompt,
            'url'    => $_SESSION['verify']['url'],
        ]);

        $view->setTemplate('verify');
        return $view;
    }

    protected function showVerifyIfNeeded($prompt = 'Please Verify Your Number')
    {
        //check if we're in the middle of a verification
        if(isset($_SESSION['verify']['request'])){
            $response = $this->verify->search([
                'request_id' => $_SESSION['verify']['request']
            ]);

            if(isset($response['status']) AND 'IN PROGRESS' == $response['status']){
                return $this->verifyPrompt($prompt);
            }
        }
    }

    protected function checkCode($code)
    {
        $response = $this->verify->check([
            'request_id' => $_SESSION['verify']['request'],
            'code' => $code
        ]);

        if($response['status'] != 0){
            $this->flashMessenger()->addErrorMessage($response['error_text']);
            return false;
        }

        return true;
    }
}