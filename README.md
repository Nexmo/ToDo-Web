# [How-To] Adding Verify to a Web App

## Introduction
Almost every web developer has built a login page, and probably more than one. To show how to protect a login with phone 
number powered second factor authentication (2FA), we'll walk through the process of adding Nexmo's Verify API to an 
exsisting web application.

Our web application is a simple todo list. All the application data, and even the login, is powered by Parse. That 
will allow us to focus on the code that powers 2FA and abstract away the code needed to persist todo items and store 
passwords. It also means running this example app yourself only requires PHP and a Parse account.

All service credentials are stored in a [local config file][local_config] which can be created by hand or by running
[`$php public/index.php setup config`][config_setup]

Our Parse database structure is pretty simple, we have a `User` class with `username` and `password`, as well as a 
`ToDo` class with a `todoItem` and a pointer to the `user` that owns the item. To set up your own copy, you can use 
[this dump of the Parse schema][schema] or run [`$php public/index.php setup parse`][parse_setup] after you've setup a 
Parse application.

The application itself is built as a rather standard Zend Framework 2 module based on the Action Controller concept. How 
the application is configured isn't too relevant to this example; however, the bulk of that is in the [module 
configuration][module_config], where you'll see the application's routes defined.

There are only two controllers, one for the application itself (the [`AppController`][app]) and one for creating 
accounts and authenticating (the [`AuthController`][auth]). Parse is really doing all the hard work for the 
[`AuthController`][auth], creating users:

    $user = new ParseUser();
    $user->setUsername($email);
    $user->setPassword($password);

Logging them in:

    $user = ParseUser::logIn($this->request->getPost('email'), $this->request->getPost('password'));
    $_SESSION['todo']['user'] = $user->getUsername();
    
Then redirecting to the `AppController`:

     $this->redirect()->toRoute('app');
     
You can take a look at the entire [`AuthController`][auth] before 2FA is added.

For any requests to the Nexmo API we'll be using Philip Shipley's client library. It's a simple wrapper [built on Guzzle
and its Web Service Clients][client]. We can add a factory to create and configure the client library when needed. That 
means we also need to add Nexmo API credentials to our [local config file][local_config] along with the Parse 
credentials.

## Adding to Signup
Before enabling second factor when signing in, the ToDo list application needs to have the user's phone number. The 
easiest way to ensure we have that - and confirm that it is really the user's number - is use the Verify API to make 
number confirmation part of the signup process as well.

This also helps avoid spoof accounts, as a user must provide their phone number when they signup and we can force that
number to be unique per user.

First we need to add a phone number field to our database. Since we're using Parse, we can go to the Parse dashboard and
add a `phoneNumber` string to the `User` class. But we don't even have to do that, as setting the field will 
automatically create it in Parse.

We also need to add that field to the signup form, an easy addition to [the signup template][signup_template]. Following
the bootstrap borrowed markup, we just add another input element:

    <label for="phone" class="sr-only">Phone Number</label>
    <input type="text" id="phone" name="phone" class="form-control" placeholder="Phone Number" required>

Now in the [`AuthController`][auth] we need to delay creating the user until they've verified the phone number they 
provided, and we've checked that the number is unique to their account. Verifying ownership of the phone number is where
we start using Nexmo's Verify API. 

Verifying a number takes two steps. First our application makes a [verfy request][verify_request] to the API and gets a 
`request_id` in response. This starts a process where the user is sent a numeric code by SMS (or should SMS not be 
successful, by a voice call).

Once the user provides that code to our application, an API request is made to the [verify check][verify_check] endpoint
to verify that the user provided the correct code. If they did, they've confirmed ownership of the device that was sent
the code.

You can take a look at Nexmo's [Verify Quickstart][verify_quickstart] or the [full Verify docs][verify_ref] for more 
information. 

To keep things simple, we'll keep everything signup related submitting to the controller's [`signupAction()`][signup_action]. 
That means the method needs to be aware of two potential requests: the initial POST where the user provides their
email, password, and phone number, and the follow up POST where the user provides the verification code they received 
on their phone.

Checking the POST data for a `code` parameter is a simple way to determine which kind of request we're handling. If 
there is no `code` parameter, we'll assume this is the initial request with all the user data. 

    if(!$this->request->getPost('code')){

Because we're delaying the creation of the user until the number is verified the user's data can be stored in the 
`$_SESSION` for now.
    
    $_SESSION['signup']['email'] = $this->request->getPost('email');
    $_SESSION['signup']['password'] = $this->request->getPost('password');

We need to ensure that the number is in international format before verifying it. We can use Nexmo's Number Insight API 
to do that, so we'll grab the NI client library from the service locator. Nexmo's Number Insight API makes it easy to 
find the international format of any number. We need to provide a default country code as well, and to keep things simple
we'll set that to `US`; however, it could be dynamically determined based on the IP address of the user.

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

If for some reason we don't get an internationally formatted number back from the Number Insight API, we'll fallback to 
the number the user provided.

Once we have a well formatted number we can start the verification process. Making a request to the verify API requires
two parameters, the number you want to verify and the 'brand' to display to the user. 

    $response = $this->verify->verify([
        'number' =>  $number,
        'brand' => 'ToDo List'
    ]);

Like the Number Insight client, we pull `$this->verify` from the service locator. Because we'll use it more than once, 
we pull it from the service locator when the request is dispatched by overriding the `onDispatch()` method. Using a 
[client library][client] makes calling the API simple. However, behind the scenes it's just turning those parameters 
into HTTP parameters, adding the credentials the client library was initialized with, and parsing the API response JSON 
into an array.

We can check the `status` property of the response to confirm that the verification process started. If `status` is 
anything other than `0`, there was a problem with the request. In that case we'll take the `error_text` from the 
response, and notify the user using the `flashMessenger()` helper in ZF2. The view is [already setup][flash_messenger] 
to display any messages to the user.

    if($response['status'] != 0){
        $this->flashMessenger()->addErrorMessage($response['error_text']);
        return;
    }

If the verification process was successful, then we just need to set a `$_SESSION` variable. `request` will store the 
current verification request ID, which we'll use to verify that the user has provided us with the right code.  

    $_SESSION['verify']['request'] = $response['request_id'];

Now we need to prompt the user for the code. But before we do that, let's take a step back. This is a process that will
happen more than once. We'll be doing a verification during signup, a verification during signin, and potentially a 
verification for other critical actions.
 
So it makes sense to abstract this whole process into something reusable. Since we're using PHP, that'll be a method
on a trait so we can use it in any other class. In other languages you might some other form of mixin, regular class 
inheritance, or wrap it in a standalone object that can be reused.

    class AuthController extends AbstractActionController
    {
        use VerifyTrait;
        //...

To start things off, we'll move the code that fetches a Verify client library in the `onDispatch()` method. If you're 
following along, just [grab the full trait code][trait] and remove all but the `onDispatch()` method.

We'll create a `startVerification()` to start the verification process, and a `verifyPrompt()` method to 
display the confirmation form. `startVerification()` is just the code we had added to the `signupAction()`. But since we 
can use this in multiple controllers, we may need to change what URL the verification prompt submits the code to. That 
will only be relevant for a single verification request, so we allow an optional `url` to be passed to 
`startVerification()` and store it in the `$_SESSION`.

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

The `verifyPrompt()` method is rather simple. By default, ZendFramework just renders the view template that matches 
the name of the controller's action. `verifyPrompt()` sets up a custom template that [prompts the user for a 
code][verify_template]. It can also allow us to customize the prompt.

    protected function verifyPrompt($prompt)
    {
        $view = new ViewModel([
            'prompt' => $prompt,
            'url'    => $_SESSION['verify']['url'],
        ]);

        $view->setTemplate('verify');
        return $view;
    }

Now that we've abstracted the common functionality, we can add a few lines to the `signupAction()`, starting the 
verification process, and prompting the user for the verification code they were sent.

    $this->startVerification($_SESSION['signup']['phone']);
    return $this->verifyPrompt('Please Verify Your Number');

But there's still an issue if the user hits refresh, sending the same data again, the application trying to start the
verification process a second time. While Nexmo's Verify API won't allow a concurrent verification request for the same 
number, avoiding that accidental refresh entirely creates a much better user experience.

Redirecting the user after submitting a form is a common technique used to avoid that situation. In this case, using 
that pattern will also simplify handling incorrect verification codes. 
   
So back in our trait, we add a rather verbosely named `showVerifyIfNeeded()` method that will - in case you can't 
guess it - show a verification prompt if needed. The logic is rather straightforward. If the `$_SESSION` has a request
ID, and if that request is currently in progress, return the verification prompt. 

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

Like `verifyPrompt()` we allow a custom prompt. We also use the Verify API to search for a verification by ID, so we 
can check the status. The verification prompt will only be returned for verifications that are in progress. Those are 
verifications where the code can still be checked. Any verification that has expired due to the time lapsed, or has
failed due to too many invalid codes, will not return the verification prompt.

We can now use `showVerifyIfNeeded()` in our `signupAction()`. At the start of the method we already check to see if 
the request was a POST. We return if it was not as there's nothing to do except show the default view template with the
signup form. Now we return the verification prompt if we're in the middle of a verification.

    if(!($this->request instanceof Request) OR !$this->request->isPost()){
        return $this->showVerifyIfNeeded();
    }
    
On the initial POST to the `signupAction()` to create the user, once the verification process is started we redirect 
back to the same action. If the verification was successfully started the prompt will be displayed. If it was not, the 
error was passed to the user in the flash messenger, and will be displayed after the redirect. 
    
    return $this->redirect()->toRoute('auth', ['action' => 'signup']);
    
Now that we have a pretty robust and reusable way to start the verification process, we need to check the code the user
submits and if it is correct, create the user's account. The conditional code we added to start the verification process
is skipped when the user submits a `code`.

    if(!$this->request->getPost('code')){
        //...
        return $this->redirect()->toRoute('auth', ['action' => 'signup']);
    }

The original code that created the Parse user has to be updated to use the data we stored in `$_SESSION`, and to add the
verified phone number to the user's account.
 
    $email = $_SESSION['signup']['email'];
    $password = $_SESSION['signup']['password'];
    $phone = $_SESSION['signup']['phone'];
    
    $user = new ParseUser();
    $user->setUsername($email);
    $user->setPassword($password);
    $user->set('phoneNumber', $phone);

The rest of the user creation stays the same. But we need to ensure this code is only reached when the user submits the 
correct `code`, not just any code. 

Checking the user provided code is something we'll do everywhere, so we'll add a `checkCode()` method to our trait. 
Since we stored the request ID in `$_SESSION` we only need to pass the method the user's code. 

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
    
If the API response is unsuccessful, we'll add the error as a flash message. The calling code can use the return value
of the method to determine if the check was successful or not. Now in our `signupAction()` we just add a call to 
`checkCode()` before creating the user.

    if(!$this->checkCode($this->request->getPost('code'))){
        return $this->redirect()->toRoute('auth', ['action' => 'signup']);
    }
    
At this point we can start to see the advantage of `showVerifyIfNeeded()`. Should a user submit the incorrect 
verification code, they'll be redirected back to the `signupAction()`, and if the verification is still active they'll 
be prompted again. If they've entered an incorrect code too many times, or of the verification has timed out, they'll 
see the signup form and can start again. In either case the error will also be displayed due to the flash message.

## Protecting Signin 
Now that users have a verified phone number on their account, we can enable second factor authentication on signin. 
Since we're using the `signingAction()` as a cheap way to do sign users out of the application, we have to relocate the 
rather aggressive `ParseUser::logout()` so it doesn't logout users that are in the middle of a verification process. 
If there's a Parse user and `$_SESSION['todo']['user']` has the same username, we need to sign the user out. Any other 
condition is an inprocess signin.

    if($user = ParseUser::getCurrentUser() AND $_SESSION['todo']['user'] == $user->getUsername()){
        ParseUser::logOut();
        $_SESSION['todo']['user'] = null;
    }

Using the same pattern as we did in the `signupAction()` we'll take any non-POST and show a verification prompt if 
needed. And the same conditional handles starting the verification process on the initial signin request.

    if(!($this->request instanceof Request) OR !$this->request->isPost()){
        return $this->showVerifyIfNeeded();
    }
    
    if(!$this->request->getPost('code')){
    //...

We'll put the original signin code in that conditional. Any incorrect username and password will just redirect to the 
signin form, and the error will be passed in the flash messenger. But now we'll make sure the `$_SESSION` key we use to 
track the signed in user is set to null, as we don't want just a correct username and password to signin the user.

    ParseUser::logOut();
    try {
        $user = ParseUser::logIn($this->request->getPost('email'), $this->request->getPost('password'));
        $_SESSION['todo']['user'] = null;
    } catch (ParseException $e) {
        $this->flashMessenger()->addErrorMessage($e->getMessage());
        return $this->redirect()->toRoute('auth', ['action' => 'signin']);
    }
    
If the login is successful, then we'll start a verification process, just like the `signupAction()`. Only now the number
will come from the authenticated user. Like before, regardless of the outcome of the verification process we'll redirect
to the `signinAction()` and let `showVerifyIfNeeded()` handle the verification prompt. 

    $this->startVerification($user->get('phoneNumber'));
    return $this->redirect()->toRoute('auth', ['action' => 'signin']);
    
If the user provides a 'code', and there's a logged in user in the Parse session, the conditional is skipped. The code 
is checked, and if invalid the user is redirected to the `signinAction()` where they are prompted again if the 
verification process is still active. If it is not, they have to start the login process again.

    if(!$this->checkCode($this->request->getPost('code'))){
        return $this->redirect()->toRoute('auth', ['action' => 'signin']);
    }
    
    $user = ParseUser::getCurrentUser();
    $_SESSION['todo']['user'] = $user->getUsername();
    $this->redirect()->toRoute('app');
    
If the code is valid, the login success is stored in the `$_SESSION` just like before, and the user redirected to the 
`AppController`. 

Abstracting the verification process made enabling second factor on the signin a simple change. But we can take it one
step further.

## Second Factor for Delete
Our ToDo list items are pretty important. Should we walk away from our computer and not lock it, we wouldn't want some
random co-worker to stop by and delete them all. We can prevent that catastrophic possibility by adding second factor
authentication to the delete process of the ToDo list application.

Since this is the first time we're doing a verification process in the [`AppController`][app] we need to add a use 
statements for the [`VerifyTrait`][trait]

    class AppController extends AbstractActionController
    {
        use VerifyTrait;
        //...

Our `deleteAction()` expects a POST with the ID of the todo item, queries Parse to find that item, and then destroys it.
Once done, it redirects to the main `AppController` action which just renders a list of items to do.

    $query = new ParseQuery(self::PARSE_CLASS);
    try {
        $item = $query->get($this->request->getPost('id'));
        $item->destroy();
    } catch (ParseException $e) {
        $this->flashMessenger()->addErrorMessage($e->getMessage());
    }
    $this->redirect()->toRoute('app');
 
Following the pattern of signup and signin, we add a conditional to check if the user POSTed a `code`. If they didn't,
the id is stored in the `$_SESSION`, we start a verification, then we redirect to the main app.

    if(!$this->request->getPost('code')){
        $_SESSION['todo']['delete'] = $this->request->getPost('id');
        $this->startVerification($this->user->get('phoneNumber', '/app/delete'));
        $this->redirect()->toRoute('app');
    }
    
The only thing different this time is that we provide `startVerification()` with a URL that sets where the form is 
submitted. This allows us to always redirect the `deleteAction()` back to the main `indexAction()`, but still have the 
verification code submitted to the `deleteAction()`.

When a `code` is sent to the `deleteAction()` it's checked and if it's valid the item is deleted.

    $code = $this->request->getPost('code');
    if($this->checkCode($code)){
        $query = new ParseQuery(self::PARSE_CLASS);
        try {
            $item = $query->get($_SESSION['todo']['delete']);
            $item->destroy();
            $_SESSION['todo']['delete'] = null;
        } catch (ParseException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }
    }
    
    $this->redirect()->toRoute('app');
    
Since there's never any view for the `deleteAction()`, every request is redirected to the main app action 
`indexAction()`. It's there we add `showVerifyIfNeeded()` to handle prompting the user for a verification code.

    if($view = $this->showVerifyIfNeeded()){
        return $view;
    }

## Next Steps
Adding second factor authentication to your application is not a complex process with Nexmo's Verify API. At this point 
we have a nicely abstracted way to add phone number powered second factor to any part of our application. However, there 
are things that are obviously missing or could be better. Here are a couple big ones to keep in mind.

Our application is simple, and there's no place to update the user's phone number (or even password). When adding 
phone numbers as a second factor, you'll also need to add a way for the user to update that phone number. And there's 
no way to have users add a phone number, if they signed up *before* support for second factor login was added.

Error handling in this example is at best simplistic. If it's not possible to get the international format of the 
number from the Number Insight API, that's a signal that there's probably something wrong with the number the user 
provided. And just echoing the error message from the Verify API to the user makes things simple, but it's definitely 
better to check the error code and provide the user with a message crafted to help them know what to do next.

You'll also find a few anti-patterns, like accessing the service locator from the controller instead of injecting all 
dependencies, sharing the $_SESSION global across all the controllers, or using a trait that is directly tied to an 
abstract class. All can be avoided with proper dependency injection; but that would make this example a bit harder to 
follow. 

Now that you've seen how easy it is to add second factor authentication to signin or any other important part of 
your application, add it to your application today and keep your user's accounts secure.

[local_config]: config/autoload/local.php.dist
[config_setup]: module/Todo/src/SetupController.php
[parse_setup]: module/Todo/src/SetupController.php
[schema]: schema.json
[module_config]: module/Todo/config/module.config.php
[app]: module/Todo/src/AppController.php
[auth]: module/Todo/src/AuthController.php
[client]: http://www.phillipshipley.com/2015/04/creating-a-php-nexmo-api-client-using-guzzle-web-service-client-part-1/
[signup_template]: module/Todo/view/signup.phtml
[verify_request]: https://docs.nexmo.com/api-ref/verify
[verify_check]: https://docs.nexmo.com/api-ref/verify/check
[verify_quickstart]: https://developers.nexmo.com/Quickstarts/verify/verify/
[verify_ref]: https://docs.nexmo.com/api-ref/verify
[trait]: module/Todo/src/VerifyTrait.php
[verify_template]: module/Todo/view/verify.phtml

[signup_action]: module/Todo/src/Todo/Controller/AuthController.php#L14
[flash_messenger]: view/layout/layout.phtml
