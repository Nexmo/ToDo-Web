<?php
namespace Todo;
use Parse\ParseUser;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Mvc\MvcEvent;

trait VerifyTrait
{
    /**
     * @var \Nexmo\Verify
     */
    protected $verify;

    /* @var \Google\Authenticator\GoogleAuthenticator */
    protected $auth;

    public function onDispatch(MvcEvent $e)
    {
        //trait only valid for a instance of abstract action controller
        if(!$this instanceof AbstractActionController){
            throw new \Exception('Must extend AbstractActionController to use VerifyTrait');
        }

        //get verify
        $this->verify = $this->getServiceLocator()->get('Nexmo\Verify');
        $this->auth = $this->getServiceLocator()->get('GoogleAuthenticator');
        return parent::onDispatch($e);
    }

    protected function startVerification($number, $url = null, ParseUser $user = null)
    {
        $_SESSION['verify']['started'] = true;
        $_SESSION['verify']['url'] = $url;
        $_SESSION['verify']['number'] = $number;
        unset($_SESSION['verify']['request']);

        //if no totp, just start the process
        if(!$user OR !$user->get('totp')){
            $this->deliverCode($number);
        } else {
            $_SESSION['verify']['totp'] = $user->get('totp');
        }
    }

    protected function deliverCode($number)
    {
        $response = $this->verify->verify([
            'number' =>  $number,
            'brand' => 'ToDo List'
        ]);

        if($response['status'] != 0){
            $this->flashMessenger()->addErrorMessage($response['error_text']);
        }

        $_SESSION['verify']['request'] = $response['request_id'];
    }

    protected function verifyPrompt($prompt)
    {
        $view = new ViewModel([
            'prompt' => $prompt,
            'url'    => $_SESSION['verify']['url'],
            'sent'   => isset($_SESSION['verify']['request'])
        ]);

        $view->setTemplate('verify');
        return $view;
    }

    protected function showVerifyIfNeeded($prompt = 'Please Verify Your Number')
    {
        //are we in a verification process?
        if(!isset($_SESSION['verify']['started']) OR !$_SESSION['verify']['started']){
            return;
        }

        //should we deliver a code?
        if($this->request->getQuery('deliver')){
            $this->deliverCode($_SESSION['verify']['number']);
            return $this->redirect()->refresh();
        }

        //has code delivery failed?
        if(isset($_SESSION['verify']['request'])){
            $response = $this->verify->search([
                'request_id' => $_SESSION['verify']['request']
            ]);

            if(!isset($response['status']) OR 'IN PROGRESS' != $response['status']){
                return;
            }
        }

        return $this->verifyPrompt($prompt);
    }

    protected function checkCode($code)
    {
        //check totp
        if(isset($_SESSION['verify']['totp']) AND $this->auth->checkCode($_SESSION['verify']['totp'], $code)){
            $_SESSION['verify']['started'] = false;
            return true;
        }

        //check delivered code
        if(isset($_SESSION['verify']['request'])){
            $response = $this->verify->check([
                'request_id' => $_SESSION['verify']['request'],
                'code' => $code
            ]);

            if($response['status'] != 0){
                $this->flashMessenger()->addErrorMessage($response['error_text']);
                return false;
            }
        }

        $_SESSION['verify']['started'] = false;
        return true;
    }
}