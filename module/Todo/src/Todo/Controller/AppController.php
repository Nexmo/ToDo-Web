<?php
namespace Todo\Controller;

use Parse\ParseException;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Stdlib\RequestInterface as Request;
use Zend\Stdlib\ResponseInterface as Response;

class AppController extends AbstractActionController
{
    const PARSE_CLASS = 'ToDo';

    /**
     * @var ParseUser
     */
    protected $user;

    public function dispatch(Request $request, Response $response = null)
    {
        $this->user = ParseUser::getCurrentUser();
        if(!$this->user){
            return $this->redirect()->toRoute('auth', ['action' => 'signin']);
        }

        return parent::dispatch($request, $response); // TODO: Change the autogenerated stub
    }


    public function indexAction()
    {
        $query = new ParseQuery(self::PARSE_CLASS);
        $query->equalTo('user', $this->user);

        $items = $query->find();
        return [
            'items' => $items
        ];
    }

    public function addAction()
    {
        if(!($this->request instanceof \Zend\Http\Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        $item = new ParseObject(self::PARSE_CLASS);
        $item->set('todoItem', $this->request->getPost('item'));
        $item->set('user', $this->user);

        try {
            $item->save();
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        $this->redirect()->toRoute('app');
    }

    public function deleteAction()
    {
        if(!($this->request instanceof \Zend\Http\Request) OR !$this->request->isPost()){
            return; //nothing to do
        }

        $query = new ParseQuery(self::PARSE_CLASS);
        try {
            $item = $query->get($this->request->getPost('id'));
            $item->destroy();
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        $this->redirect()->toRoute('app');
    }
}