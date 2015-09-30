<?php
namespace Todo\Controller;
use Parse\ParseException;
use Parse\ParseUser;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;

class AuthController extends AbstractActionController
{
    public function signinAction()
    {
        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        try {
            $user = ParseUser::logIn($this->request->getPost('email'), $this->request->getPost('password'));
            $this->redirect()->toRoute('app');
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('auth', ['action' => 'signin']);
        }
    }

    public function signupAction()
    {
        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = new ParseUser();
        $user->setUsername($email);
        $user->setPassword($password);

        try {
            $user->signUp();
            $this->redirect()->toRoute('app');
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('auth', ['action' => 'signup']);
        }
    }
}