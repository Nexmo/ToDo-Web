<?php
namespace Todo;
use Parse\ParseException;
use Parse\ParseUser;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;

class AuthController extends AbstractActionController
{
    use VerifyTrait;

    /**
     * Expects a post with email / password (or the form is just shown). Creates a new user (if possible) then redirects
     * to the app controller on success, or itself (PRG) with a flash message on error.
     */
    public function signupAction()
    {
        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return $this->showVerifyIfNeeded();
        }

        //no verification code, so store signup data in session
        if(!$this->request->getPost('code')){
            //store in session to use after the number is verified
            $_SESSION['signup']['email'] = $this->request->getPost('email');
            $_SESSION['signup']['password'] = $this->request->getPost('password');

            //get a NI client, and normalize the number
            $ni = $this->getServiceLocator()->get('Nexmo\Insight');
            $result = $ni->basic([
                'number'  => $this->request->getPost('phone'),
                'country' => 'US'
            ]);

            if(isset($result['international_format_number'])){
                $_SESSION['signup']['phone'] = $result['international_format_number'];
            } else {
                $_SESSION['signup']['phone'] = $this->request->getPost('phone');
            }

            $this->startVerification($_SESSION['signup']['phone']);
            return $this->redirect()->toRoute('auth', ['action' => 'signup']);
        }

        //check that the code is correct
        if(!$this->checkCode($this->request->getPost('code'))){
            return $this->redirect()->toRoute('auth', ['action' => 'signup']);
        }

        $email = $_SESSION['signup']['email'];
        $password = $_SESSION['signup']['password'];
        $phone = $_SESSION['signup']['phone'];

        $user = new ParseUser();
        $user->setUsername($email);
        $user->setPassword($password);
        $user->set('phoneNumber', $phone);

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
        if($user = ParseUser::getCurrentUser() AND $_SESSION['todo']['user'] == $user->getUsername()){
            ParseUser::logOut();
            $_SESSION['todo']['user'] = null;
        }

        if(!($this->request instanceof Request) OR !$this->request->isPost()){
            return $this->showVerifyIfNeeded();
        }

        if(!$this->request->getPost('code')){
            try {
                $user = ParseUser::logIn($this->request->getPost('email'), $this->request->getPost('password'));
                $_SESSION['todo']['user'] = null;
            } catch (ParseException $e) {
                $this->flashMessenger()->addErrorMessage($e->getMessage());
                return $this->redirect()->toRoute('auth', ['action' => 'signin']);
            }

            $this->startVerification($user->get('phoneNumber'));
            return $this->redirect()->toRoute('auth', ['action' => 'signin']);
        }

        if(!$this->checkCode($this->request->getPost('code'))){
            return $this->redirect()->toRoute('auth', ['action' => 'signin']);
        }

        $user = ParseUser::getCurrentUser();
        $_SESSION['todo']['user'] = $user->getUsername();
        $this->redirect()->toRoute('app');
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