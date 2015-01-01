<?php
/**
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\TestSuite;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Session;
use Cake\Routing\DispatcherFactory;
use Cake\Routing\Router;
use Cake\TestSuite\Stub\Response;
use Cake\TestSuite\TestCase;

/**
 * A test case class intended to make integration tests of
 * your controllers easier.
 *
 * This test class provides a number of helper methods and features
 * that make dispatching requests and checking their responses simpler.
 * It favours full integration tests over mock objects as you can test
 * more of your code easily and avoid some of the maintenance pitfalls
 * that mock objects create.
 */
abstract class IntegrationTestCase extends TestCase
{

    /**
     * The data used to build the next request.
     *
     * @var array
     */
    protected $_request = [];

    /**
     * The response for the most recent request.
     *
     * @var \Cake\Network\Response
     */
    protected $_response;

    /**
     * Session data to use in the next request.
     *
     * @var array
     */
    protected $_session = [];

    /**
     * Cookie data to use in the next request.
     *
     * @var array
     */
    protected $_cookie = [];

    /**
     * The controller used in the last request.
     *
     * @var \Cake\Controller\Controller
     */
    protected $_controller;

    /**
     * The last rendered view
     *
     * @var string
     */
    protected $_viewName;

    /**
     * The last rendered layout
     *
     * @var string
     */
    protected $_layoutName;

    /**
     * The session instance from the last request
     *
     * @var \Cake\Network\Session
     */
    protected $_requestSession;

    /**
     * Resets the EventManager for before each test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        EventManager::instance(new EventManager());
    }

    /**
     * Clears the state used for requests.
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->_request = [];
        $this->_session = [];
        $this->_cookie = [];
        $this->_response = null;
        $this->_controller = null;
        $this->_viewName = null;
        $this->_layoutName = null;
        $this->_requestSession = null;
    }

    /**
     * Configures the data for the *next* request.
     *
     * This data is cleared in the tearDown() method.
     *
     * You can call this method multiple times to append into
     * the current state.
     *
     * @param array $data The request data to use.
     * @return void
     */
    public function configRequest(array $data)
    {
        $this->_request = $data + $this->_request;
    }

    /**
     * Sets session data.
     *
     * This method lets you configure the session data
     * you want to be used for requests that follow. The session
     * state is reset in each tearDown().
     *
     * You can call this method multiple times to append into
     * the current state.
     *
     * @param array $data The session data to use.
     * @return void
     */
    public function session(array $data)
    {
        $this->_session = $data + $this->_session;
    }

    /**
     * Sets a request cookie for future requests.
     *
     * This method lets you configure the session data
     * you want to be used for requests that follow. The session
     * state is reset in each tearDown().
     *
     * You can call this method multiple times to append into
     * the current state.
     *
     * @param string $name The cookie name to use.
     * @param mixed $value The value of the cookie.
     * @return void
     */
    public function cookie($name, $value)
    {
        $this->_cookie[$name] = $value;
    }

    /**
     * Performs a GET request using the current request data.
     *
     * The response of the dispatched request will be stored as
     * a property. You can use various assert methods to check the
     * response.
     *
     * @param string|array $url The URL to request.
     * @return void
     */
    public function get($url)
    {
        $this->_sendRequest($url, 'GET');
    }

    /**
     * Performs a POST request using the current request data.
     *
     * The response of the dispatched request will be stored as
     * a property. You can use various assert methods to check the
     * response.
     *
     * @param string|array $url The URL to request.
     * @param array $data The data for the request.
     * @return void
     */
    public function post($url, $data = [])
    {
        $this->_sendRequest($url, 'POST', $data);
    }

    /**
     * Performs a PATCH request using the current request data.
     *
     * The response of the dispatched request will be stored as
     * a property. You can use various assert methods to check the
     * response.
     *
     * @param string|array $url The URL to request.
     * @param array $data The data for the request.
     * @return void
     */
    public function patch($url, $data = [])
    {
        $this->_sendRequest($url, 'PATCH', $data);
    }

    /**
     * Performs a PUT request using the current request data.
     *
     * The response of the dispatched request will be stored as
     * a property. You can use various assert methods to check the
     * response.
     *
     * @param string|array $url The URL to request.
     * @param array $data The data for the request.
     * @return void
     */
    public function put($url, $data = [])
    {
        $this->_sendRequest($url, 'PUT', $data);
    }

    /**
     * Performs a DELETE request using the current request data.
     *
     * The response of the dispatched request will be stored as
     * a property. You can use various assert methods to check the
     * response.
     *
     * @param string|array $url The URL to request.
     * @return void
     */
    public function delete($url)
    {
        $this->_sendRequest($url, 'DELETE');
    }

    /**
     * Creates and send the request into a Dispatcher instance.
     *
     * Receives and stores the response for future inspection.
     *
     * @param string|array $url The URL
     * @param string $method The HTTP method
     * @param array|null $data The request data.
     * @return void
     * @throws \Exception
     */
    protected function _sendRequest($url, $method, $data = [])
    {
        $request = $this->_buildRequest($url, $method, $data);
        $response = new Response();
        $dispatcher = DispatcherFactory::create();
        $dispatcher->eventManager()->attach(
            [$this, 'controllerSpy'],
            'Dispatcher.beforeDispatch',
            ['priority' => 999]
        );
        try {
            $dispatcher->dispatch($request, $response);
            $this->_requestSession = $request->session();
            $this->_response = $response;
        } catch (\PHPUnit_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->_handleError($e);
        }
    }

    /**
     * Adds additional event spies to the controller/view event manager.
     *
     * @param \Cake\Event\Event $event A dispatcher event.
     * @return void
     */
    public function controllerSpy($event)
    {
        if (empty($event->data['controller'])) {
            return;
        }
        $this->_controller = $event->data['controller'];
        $events = $this->_controller->eventManager();
        $events->attach(function ($event, $viewFile) {
            $this->_viewName = $viewFile;
        }, 'View.beforeRender');
        $events->attach(function ($event, $viewFile) {
            $this->_layoutName = $viewFile;
        }, 'View.beforeLayout');
    }

    /**
     * Attempts to render an error response for a given exception.
     *
     * This method will attempt to use the configured exception renderer.
     * If that class does not exist, the built-in renderer will be used.
     *
     * @param \Exception $exception Exception to handle.
     * @return void
     * @throws \Exception
     */
    protected function _handleError($exception)
    {
        $class = Configure::read('Error.exceptionRenderer');
        if (empty($class) || !class_exists($class)) {
            $class = 'Cake\Error\ExceptionRenderer';
        }
        $instance = new $class($exception);
        $this->_response = $instance->render();
    }

    /**
     * Creates a request object with the configured options and parameters.
     *
     * @param string|array $url The URL
     * @param string $method The HTTP method
     * @param array|null $data The request data.
     * @return \Cake\Network\Request The built request.
     */
    protected function _buildRequest($url, $method, $data)
    {
        $sessionConfig = (array)Configure::read('Session') + [
            'defaults' => 'php',
        ];
        $session = Session::create($sessionConfig);
        $session->write($this->_session);

        $props = [
            'url' => Router::url($url),
            'post' => $data,
            'cookies' => $this->_cookie,
            'session' => $session,
        ];
        $env = [];
        if (isset($this->_request['headers'])) {
            foreach ($this->_request['headers'] as $k => $v) {
                $env['HTTP_' . str_replace('-', '_', strtoupper($k))] = $v;
            }
            unset($this->_request['headers']);
        }
        $env['REQUEST_METHOD'] = $method;
        $props['environment'] = $env;
        $props += $this->_request;
        return new Request($props);
    }

    /**
     * Fetches a view variable by name.
     *
     * If the view variable does not exist, null will be returned.
     *
     * @param string $name The view variable to get.
     * @return mixed The view variable if set.
     */
    public function viewVariable($name)
    {
        if (empty($this->_controller->viewVars)) {
            $this->fail('There are no view variables, perhaps you need to run a request?');
        }
        if (isset($this->_controller->viewVars[$name])) {
            return $this->_controller->viewVars[$name];
        }
        return null;
    }

    /**
     * Asserts that the response status code is in the 2xx range.
     *
     * @return void
     */
    public function assertResponseOk()
    {
        $this->_assertStatus(200, 204, 'Status code is not between 200 and 204');
    }

    /**
     * Asserts that the response status code is in the 4xx range.
     *
     * @return void
     */
    public function assertResponseError()
    {
        $this->_assertStatus(400, 417, 'Status code is not between 400 and 417');
    }

    /**
     * Asserts that the response status code is in the 5xx range.
     *
     * @return void
     */
    public function assertResponseFailure()
    {
        $this->_assertStatus(500, 505, 'Status code is not between 500 and 505');
    }

    /**
     * Asserts a specific response status code.
     *
     * @param int $code Status code to assert.
     * @return void
     */
    public function assertResponseCode($code)
    {
        $actual = $this->_response->statusCode();
        $this->_assertStatus($code, $code, 'Status code is not ' . $code . ' but ' . $actual);
    }

    /**
     * Helper method for status assertions.
     *
     * @param int $min Min status code.
     * @param int $max Max status code.
     * @param string $message The error message.
     * @return void
     */
    protected function _assertStatus($min, $max, $message)
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert status code.');
        }
        $status = $this->_response->statusCode();
        $this->assertGreaterThanOrEqual($min, $status, $message);
        $this->assertLessThanOrEqual($max, $status, $message);
    }

    /**
     * Asserts that the Location header is correct.
     *
     * @param string|array $url The url you expected the client to go to. This
     *   can either be a string URL or an array compatible with Router::url()
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertRedirect($url, $message = '')
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert location header. ' . $message);
        }
        $result = $this->_response->header();
        if (empty($result['Location'])) {
            $this->fail('No location header set. ' . $message);
        }
        $this->assertEquals(Router::url($url, ['_full' => true]), $result['Location'], $message);
    }

    /**
     * Asserts that the Location header is not set.
     *
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertNoRedirect($message = '')
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert location header. ' . $message);
        }
        $result = $this->_response->header();
        if (!$message) {
            $message = 'Redirect header set';
        }
        if (!empty($result['Location'])) {
            $message .= ': ' . $result['Location'];
        }
        $this->assertTrue(empty($result['Location']), $message);
    }

    /**
     * Asserts response headers
     *
     * @param string $header The header to check
     * @param string $content The content to check for.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertHeader($header, $content, $message = '')
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert headers. ' . $message);
        }
        $headers = $this->_response->header();
        if (!isset($headers[$header])) {
            $this->fail("The '$header' header is not set. " . $message);
        }
        $this->assertEquals($headers[$header], $content, $message);
    }

    /**
     * Asserts content type
     *
     * @param string $type The content-type to check for.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertContentType($type, $message = '')
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert content-type. ' . $message);
        }
        $alias = $this->_response->getMimeType($type);
        if ($alias !== false) {
            $type = $alias;
        }
        $result = $this->_response->type();
        $this->assertEquals($type, $result, $message);
    }

    /**
     * Asserts content exists in the response body.
     *
     * @param string $content The content to check for.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertResponseContains($content, $message = '')
    {
        if (!$this->_response) {
            $this->fail('No response set, cannot assert content. ' . $message);
        }
        $this->assertContains($content, $this->_response->body(), $message);
    }

    /**
     * Asserts that the search string was in the template name.
     *
     * @param string $content The content to check for.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertTemplate($content, $message = '')
    {
        if (!$this->_viewName) {
            $this->fail('No view name stored. ' . $message);
        }
        $this->assertContains($content, $this->_viewName, $message);
    }

    /**
     * Asserts that the search string was in the layout name.
     *
     * @param string $content The content to check for.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertLayout($content, $message = '')
    {
        if (!$this->_layoutName) {
            $this->fail('No layout name stored. ' . $message);
        }
        $this->assertContains($content, $this->_layoutName, $message);
    }

    /**
     * Asserts session contents
     *
     * @param string $expected The expected contents.
     * @param string $path The session data path. Uses Hash::get() compatible notation
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertSession($expected, $path, $message = '')
    {
        if (empty($this->_requestSession)) {
            $this->fail('There is no stored session data. Perhaps you need to run a request?');
        }
        $result = $this->_requestSession->read($path);
        $this->assertEquals($expected, $result, 'Session content differs. ' . $message);
    }

    /**
     * Asserts cookie values
     *
     * @param string $expected The expected contents.
     * @param string $name The cookie name.
     * @param string $message The failure message that will be appended to the generated message.
     * @return void
     */
    public function assertCookie($expected, $name, $message = '')
    {
        if (empty($this->_response)) {
            $this->fail('Not response set, cannot assert cookies.');
        }
        $result = $this->_response->cookie($name);
        $this->assertEquals($expected, $result['value'], 'Cookie data differs. ' . $message);
    }
}
