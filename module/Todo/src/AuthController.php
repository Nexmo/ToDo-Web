<?php
namespace Todo;
use Parse\ParseException;
use Parse\ParseUser;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;

class AuthController extends AbstractActionController
{
    /**
     * Expects a post with email / password (or the form is just shown). Creates a new user (if possible) then redirects
     * to the app controller on success, or itself (PRG) with a flash message on error.
     */
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
            $_SESSION['todo']['user'] = $user->getUsername();
            $this->redirect()->toRoute('app');
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('auth', ['action' => 'signup']);
        }
    }

    /**
     * Expects a post with email / password (or the form is just shown). Attempts to log the user in, then redirects
     * to the app controller. If the login fails, redirects to itself (PRG) with a flash message.
     */
    public function signinAction()
    {
        ParseUser::logOut();

        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        try {
            $user = ParseUser::logIn($this->request->getPost('email'), $this->request->getPost('password'));
            $_SESSION['todo']['user'] = $user->getUsername();
            $this->redirect()->toRoute('app');
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            $this->redirect()->toRoute('auth', ['action' => 'signin']);
        }
    }

    /**
     * Expects a post with email (or the form is just shown). Resets the password using the email then redirects to the
     * sign in page with a success or error message.
     */
    public function forgotAction()
    {
        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        $email = $this->request->getPost('email');

        try{
            ParseUser::requestPasswordReset($email);
            $this->flashMessenger()->addInfoMessage('Reset Sent!');
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        $this->redirect()->toRoute('auth', ['action' => 'signin']);
    }
}