<?php
/**
 * Methods for displaying presentation data in the view.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\View;

use Cake\Cache\Cache;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Object;
use Cake\Core\Plugin;
use Cake\Error;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Routing\RequestActionTrait;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Cake\Utility\ViewVarsTrait;

/**
 * View, the V in the MVC triad. View interacts with Helpers and view variables passed
 * in from the controller to render the results of the controller action. Often this is HTML,
 * but can also take the form of JSON, XML, PDF's or streaming files.
 *
 * CakePHP uses a two-step-view pattern. This means that the view content is rendered first,
 * and then inserted into the selected layout. This also means you can pass data from the view to the
 * layout using `$this->set()`
 *
 * Since 2.1, the base View class also includes support for themes by default. Theme views are regular
 * view files that can provide unique HTML and static assets. If theme views are not found for the
 * current view the default app view files will be used. You can set `$this->theme = 'mytheme'`
 * in your Controller to use the Themes.
 *
 * Example of theme path with `$this->theme = 'SuperHot';` Would be `app/View/Themed/SuperHot/Posts`
 *
 * @property      CacheHelper $Cache
 * @property      FormHelper $Form
 * @property      HtmlHelper $Html
 * @property      NumberHelper $Number
 * @property      PaginatorHelper $Paginator
 * @property      RssHelper $Rss
 * @property      SessionHelper $Session
 * @property      TextHelper $Text
 * @property      TimeHelper $Time
 * @property      ViewBlock $Blocks
 */
class View extends Object {

	use RequestActionTrait;
	use ViewVarsTrait;

/**
 * Helpers collection
 *
 * @var HelperRegistry
 */
	public $Helpers;

/**
 * ViewBlock instance.
 *
 * @var ViewBlock
 */
	public $Blocks;

/**
 * Name of the plugin.
 *
 * @link http://manual.cakephp.org/chapter/plugins
 * @var string
 */
	public $plugin = null;

/**
 * Name of the controller.
 *
 * @var string Name of controller
 */
	public $name = null;

/**
 * Current passed params
 *
 * @var mixed
 */
	public $passedArgs = array();

/**
 * An array of names of built-in helpers to include.
 *
 * @var mixed A single name as a string or a list of names as an array.
 */
	public $helpers = array();

/**
 * Path to View.
 *
 * @var string Path to View
 */
	public $viewPath = null;

/**
 * Name of view to use with this View.
 *
 * @var string
 */
	public $view = null;

/**
 * Name of layout to use with this View.
 *
 * @var string
 */
	public $layout = 'default';

/**
 * Path to Layout.
 *
 * @var string Path to Layout
 */
	public $layoutPath = null;

/**
 * Turns on or off CakePHP's conventional mode of applying layout files. On by default.
 * Setting to off means that layouts will not be automatically applied to rendered views.
 *
 * @var boolean
 */
	public $autoLayout = true;

/**
 * File extension. Defaults to CakePHP's template ".ctp".
 *
 * @var string
 */
	protected $_ext = '.ctp';

/**
 * Sub-directory for this view file. This is often used for extension based routing.
 * Eg. With an `xml` extension, $subDir would be `xml/`
 *
 * @var string
 */
	public $subDir = null;

/**
 * Theme name.
 *
 * @var string
 */
	public $theme = null;

/**
 * Used to define methods a controller that will be cached.
 *
 * @see Controller::$cacheAction
 * @var mixed
 */
	public $cacheAction = false;

/**
 * Holds current errors for the model validation.
 *
 * @var array
 */
	public $validationErrors = array();

/**
 * True when the view has been rendered.
 *
 * @var boolean
 */
	public $hasRendered = false;

/**
 * List of generated DOM UUIDs.
 *
 * @var array
 */
	public $uuids = array();

/**
 * An instance of a Cake\Network\Request object that contains information about the current request.
 * This object contains all the information about a request and several methods for reading
 * additional information about the request.
 *
 * @var \Cake\Network\Request
 */
	public $request;

/**
 * Reference to the Response object
 *
 * @var \Cake\Network\Response
 */
	public $response;

/**
 * The Cache configuration View will use to store cached elements. Changing this will change
 * the default configuration elements are stored under. You can also choose a cache config
 * per element.
 *
 * @var string
 * @see View::element()
 */
	public $elementCache = 'default';

/**
 * Element cache settings
 *
 * @var array
 * @see View::_elementCache();
 * @see View::_renderElement
 */
	public $elementCacheSettings = array();

/**
 * List of variables to collect from the associated controller.
 *
 * @var array
 */
	protected $_passedVars = array(
		'viewVars', 'autoLayout', 'helpers', 'view', 'layout', 'name', 'theme',
		'layoutPath', 'viewPath', 'request', 'plugin', 'passedArgs', 'cacheAction'
	);

/**
 * Scripts (and/or other <head /> tags) for the layout.
 *
 * @var array
 */
	protected $_scripts = array();

/**
 * Holds an array of paths.
 *
 * @var array
 */
	protected $_paths = array();

/**
 * The names of views and their parents used with View::extend();
 *
 * @var array
 */
	protected $_parents = array();

/**
 * The currently rendering view file. Used for resolving parent files.
 *
 * @var string
 */
	protected $_current = null;

/**
 * Currently rendering an element. Used for finding parent fragments
 * for elements.
 *
 * @var string
 */
	protected $_currentType = '';

/**
 * Content stack, used for nested templates that all use View::extend();
 *
 * @var array
 */
	protected $_stack = array();

/**
 * Instance of the Cake\Event\EventManager this View object is using
 * to dispatch inner events. Usually the manager is shared with
 * the controller, so it it possible to register view events in
 * the controller layer.
 *
 * @var \Cake\Event\EventManager
 */
	protected $_eventManager = null;

/**
 * Constant for view file type 'view'
 *
 * @var string
 */
	const TYPE_VIEW = 'view';

/**
 * Constant for view file type 'element'
 *
 * @var string
 */
	const TYPE_ELEMENT = 'element';

/**
 * Constant for view file type 'layout'
 *
 * @var string
 */
	const TYPE_LAYOUT = 'layout';

/**
 * Constructor
 *
 * @param Controller $controller A controller object to pull View::_passedVars from.
 */
	public function __construct(Controller $controller = null) {
		if (is_object($controller)) {
			$count = count($this->_passedVars);
			for ($j = 0; $j < $count; $j++) {
				$var = $this->_passedVars[$j];
				$this->{$var} = $controller->{$var};
			}
			$this->_eventManager = $controller->getEventManager();
		}
		if (empty($this->request) && !($this->request = Router::getRequest(true))) {
			$this->request = new Request();
			$this->request->base = '';
			$this->request->here = $this->request->webroot = '/';
		}
		if (is_object($controller) && isset($controller->response)) {
			$this->response = $controller->response;
		} else {
			$this->response = new Response();
		}
		$this->Helpers = new HelperRegistry($this);
		$this->Blocks = new ViewBlock();
		$this->loadHelpers();
		parent::__construct();
	}

/**
 * Returns the Cake\Event\EventManager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @return \Cake\Event\EventManager
 */
	public function getEventManager() {
		if (empty($this->_eventManager)) {
			$this->_eventManager = new EventManager();
		}
		return $this->_eventManager;
	}

/**
 * Set the Eventmanager used by View.
 *
 * Primarily useful for testing.
 *
 * @param \Cake\Event\EventManager $eventManager.
 * @return void
 */
	public function setEventManager(EventManager $eventManager) {
		$this->_eventManager = $eventManager;
	}

/**
 * Renders a piece of PHP with provided parameters and returns HTML, XML, or any other string.
 *
 * This realizes the concept of Elements, (or "partial layouts") and the $params array is used to send
 * data to be used in the element. Elements can be cached improving performance by using the `cache` option.
 *
 * @param string $name Name of template file in the/app/Template/Element/ folder,
 *   or `MyPlugin.template` to use the template element from MyPlugin. If the element
 *   is not found in the plugin, the normal view path cascade will be searched.
 * @param array $data Array of data to be made available to the rendered view (i.e. the Element)
 * @param array $options Array of options. Possible keys are:
 * - `cache` - Can either be `true`, to enable caching using the config in View::$elementCache. Or an array
 *   If an array, the following keys can be used:
 *   - `config` - Used to store the cached element in a custom cache configuration.
 *   - `key` - Used to define the key used in the Cache::write(). It will be prefixed with `element_`
 * - `callbacks` - Set to true to fire beforeRender and afterRender helper callbacks for this element.
 *   Defaults to false.
 * - `ignoreMissing` - Used to allow missing elements. Set to true to not trigger notices.
 * @return string Rendered Element
 */
	public function element($name, $data = array(), $options = array()) {
		$file = $plugin = null;

		if (!isset($options['callbacks'])) {
			$options['callbacks'] = false;
		}

		if (isset($options['cache'])) {
			$contents = $this->_elementCache($name, $data, $options);
			if ($contents !== false) {
				return $contents;
			}
		}

		$file = $this->_getElementFilename($name);
		if ($file) {
			return $this->_renderElement($file, $data, $options);
		}

		if (empty($options['ignoreMissing'])) {
			list ($plugin, $name) = pluginSplit($name, true);
			$name = str_replace('/', DS, $name);
			$file = $plugin . 'Element' . DS . $name . $this->_ext;
			trigger_error(sprintf('Element Not Found: %s', $file), E_USER_NOTICE);
		}
	}

/**
 * Checks if an element exists
 *
 * @param string $name Name of template file in the /app/Template/Element/ folder,
 *   or `MyPlugin.template` to check the template element from MyPlugin. If the element
 *   is not found in the plugin, the normal view path cascade will be searched.
 * @return boolean Success
 */
	public function elementExists($name) {
		return (bool)$this->_getElementFilename($name);
	}

/**
 * Renders view for given view file and layout.
 *
 * Render triggers helper callbacks, which are fired before and after the view are rendered,
 * as well as before and after the layout. The helper callbacks are called:
 *
 * - `beforeRender`
 * - `afterRender`
 * - `beforeLayout`
 * - `afterLayout`
 *
 * If View::$autoRender is false and no `$layout` is provided, the view will be returned bare.
 *
 * View and layout names can point to plugin views/layouts. Using the `Plugin.view` syntax
 * a plugin view/layout can be used instead of the app ones. If the chosen plugin is not found
 * the view will be located along the regular view path cascade.
 *
 * @param string $view Name of view file to use
 * @param string $layout Layout to use.
 * @return string|null Rendered content or null if content already rendered and returned earlier.
 * @throws \Cake\Error\Exception If there is an error in the view.
 */
	public function render($view = null, $layout = null) {
		if ($this->hasRendered) {
			return;
		}

		if ($view !== false && $viewFileName = $this->_getViewFileName($view)) {
			$this->_currentType = static::TYPE_VIEW;
			$this->getEventManager()->dispatch(new Event('View.beforeRender', $this, array($viewFileName)));
			$this->Blocks->set('content', $this->_render($viewFileName));
			$this->getEventManager()->dispatch(new Event('View.afterRender', $this, array($viewFileName)));
		}

		if ($layout === null) {
			$layout = $this->layout;
		}
		if ($layout && $this->autoLayout) {
			$this->Blocks->set('content', $this->renderLayout('', $layout));
		}
		$this->hasRendered = true;
		return $this->Blocks->get('content');
	}

/**
 * Renders a layout. Returns output from _render(). Returns false on error.
 * Several variables are created for use in layout.
 *
 * - `title_for_layout` - A backwards compatible place holder, you should set this value if you want more control.
 * - `content_for_layout` - contains rendered view file
 * - `scripts_for_layout` - Contains content added with addScript() as well as any content in
 *   the 'meta', 'css', and 'script' blocks. They are appended in that order.
 *
 * Deprecated features:
 *
 * - `$scripts_for_layout` is deprecated and will be removed in CakePHP 3.0.
 *   Use the block features instead. `meta`, `css` and `script` will be populated
 *   by the matching methods on HtmlHelper.
 * - `$title_for_layout` is deprecated and will be removed in CakePHP 3.0.
 *   Use the `title` block instead.
 * - `$content_for_layout` is deprecated and will be removed in CakePHP 3.0.
 *   Use the `content` block instead.
 *
 * @param string $content Content to render in a view, wrapped by the surrounding layout.
 * @param string $layout Layout name
 * @return mixed Rendered output, or false on error
 * @throws \Cake\Error\Exception if there is an error in the view.
 */
	public function renderLayout($content, $layout = null) {
		$layoutFileName = $this->_getLayoutFileName($layout);
		if (empty($layoutFileName)) {
			return $this->Blocks->get('content');
		}

		if (empty($content)) {
			$content = $this->Blocks->get('content');
		} else {
			$this->Blocks->set('content', $content);
		}
		$this->getEventManager()->dispatch(new Event('View.beforeLayout', $this, array($layoutFileName)));

		$scripts = implode("\n\t", $this->_scripts);
		$scripts .= $this->Blocks->get('meta') . $this->Blocks->get('css') . $this->Blocks->get('script');

		$this->viewVars = array_merge($this->viewVars, array(
			'content_for_layout' => $content,
			'scripts_for_layout' => $scripts,
		));

		$title = $this->Blocks->get('title');
		if ($title === '') {
			if (isset($this->viewVars['title_for_layout'])) {
				$title = $this->viewVars['title_for_layout'];
			} else {
				$title = Inflector::humanize($this->viewPath);
			}
		}
		$this->viewVars['title_for_layout'] = $title;
		$this->Blocks->set('title', $title);

		$this->_currentType = static::TYPE_LAYOUT;
		$this->Blocks->set('content', $this->_render($layoutFileName));

		$this->getEventManager()->dispatch(new Event('View.afterLayout', $this, array($layoutFileName)));
		return $this->Blocks->get('content');
	}

/**
 * Render cached view. Works in concert with CacheHelper and Dispatcher to
 * render cached view files.
 *
 * @param string $filename the cache file to include
 * @param string $timeStart the page render start time
 * @return boolean Success of rendering the cached file.
 */
	public function renderCache($filename, $timeStart) {
		$response = $this->response;
		ob_start();
		include $filename;

		$type = $response->mapType($response->type());
		if (Configure::read('debug') && $type === 'html') {
			echo "<!-- Cached Render Time: " . round(microtime(true) - $timeStart, 4) . "s -->";
		}
		$out = ob_get_clean();

		if (preg_match('/^<!--cachetime:(\\d+)-->/', $out, $match)) {
			if (time() >= $match['1']) {
				//@codingStandardsIgnoreStart
				@unlink($filename);
				//@codingStandardsIgnoreEnd
				unset($out);
				return false;
			}
			return substr($out, strlen($match[0]));
		}
	}

/**
 * Returns a list of variables available in the current View context
 *
 * @return array Array of the set view variable names.
 */
	public function getVars() {
		return array_keys($this->viewVars);
	}

/**
 * Returns the contents of the given View variable or a block.
 * Blocks are checked before view variables.
 *
 * @param string $var The view var you want the contents of.
 * @param mixed $default The default/fallback content of $var.
 * @return mixed The content of the named var if its set, otherwise $default.
 */
	public function get($var, $default = null) {
		if (!isset($this->viewVars[$var])) {
			return $default;
		}
		return $this->viewVars[$var];
	}

/**
 * Get the names of all the existing blocks.
 *
 * @return array An array containing the blocks.
 * @see ViewBlock::keys()
 */
	public function blocks() {
		return $this->Blocks->keys();
	}

/**
 * Start capturing output for a 'block'
 *
 * @param string $name The name of the block to capture for.
 * @return void
 * @see ViewBlock::start()
 */
	public function start($name) {
		$this->Blocks->start($name);
	}

/**
 * Start capturing output for a 'block' if it has no content
 *
 * @param string $name The name of the block to capture for.
 * @return void
 * @see ViewBlock::startIfEmpty()
 */
	public function startIfEmpty($name) {
		$this->Blocks->startIfEmpty($name);
	}

/**
 * Append to an existing or new block. Appending to a new
 * block will create the block.
 *
 * @param string $name Name of the block
 * @param mixed $value The content for the block.
 * @return void
 * @see ViewBlock::concat()
 */
	public function append($name, $value = null) {
		$this->Blocks->concat($name, $value);
	}

/**
 * Prepend to an existing or new block. Prepending to a new
 * block will create the block.
 *
 * @param string $name Name of the block
 * @param mixed $value The content for the block.
 * @return void
 * @see ViewBlock::concat()
 */
	public function prepend($name, $value = null) {
		$this->Blocks->concat($name, $value, ViewBlock::PREPEND);
	}

/**
 * Set the content for a block. This will overwrite any
 * existing content.
 *
 * @param string $name Name of the block
 * @param mixed $value The content for the block.
 * @return void
 * @see ViewBlock::set()
 */
	public function assign($name, $value) {
		$this->Blocks->set($name, $value);
	}

/**
 * Fetch the content for a block. If a block is
 * empty or undefined '' will be returned.
 *
 * @param string $name Name of the block
 * @param string $default Default text
 * @return string $default The block content or $default if the block does not exist.
 * @see ViewBlock::get()
 */
	public function fetch($name, $default = '') {
		return $this->Blocks->get($name, $default);
	}

/**
 * End a capturing block. The compliment to View::start()
 *
 * @return void
 * @see ViewBlock::end()
 */
	public function end() {
		$this->Blocks->end();
	}

/**
 * Provides view or element extension/inheritance. Views can extends a
 * parent view and populate blocks in the parent template.
 *
 * @param string $name The view or element to 'extend' the current one with.
 * @return void
 * @throws \LogicException when you extend a view with itself or make extend loops.
 * @throws \LogicException when you extend an element which doesn't exist
 */
	public function extend($name) {
		if ($name[0] === '/' || $this->_currentType === static::TYPE_VIEW) {
			$parent = $this->_getViewFileName($name);
		} else {
			switch ($this->_currentType) {
				case static::TYPE_ELEMENT:
					$parent = $this->_getElementFileName($name);
					if (!$parent) {
						list($plugin, $name) = $this->pluginSplit($name);
						$paths = $this->_paths($plugin);
						$defaultPath = $paths[0] . 'Element' . DS;
						throw new \LogicException(sprintf(
							'You cannot extend an element which does not exist (%s).',
							$defaultPath . $name . $this->_ext
						));
					}
					break;
				case static::TYPE_LAYOUT:
					$parent = $this->_getLayoutFileName($name);
					break;
				default:
					$parent = $this->_getViewFileName($name);
			}
		}

		if ($parent == $this->_current) {
			throw new \LogicException('You cannot have views extend themselves.');
		}
		if (isset($this->_parents[$parent]) && $this->_parents[$parent] == $this->_current) {
			throw new \LogicException('You cannot have views extend in a loop.');
		}
		$this->_parents[$this->_current] = $parent;
	}

/**
 * Adds a script block or other element to be inserted in $scripts_for_layout in
 * the `<head />` of a document layout
 *
 * @param string $name Either the key name for the script, or the script content. Name can be used to
 *   update/replace a script element.
 * @param string $content The content of the script being added, optional.
 * @return void
 * @deprecated Will be removed in 3.0. Superseded by blocks functionality.
 * @see View::start()
 */
	public function addScript($name, $content = null) {
		if (empty($content)) {
			if (!in_array($name, array_values($this->_scripts))) {
				$this->_scripts[] = $name;
			}
		} else {
			$this->_scripts[$name] = $content;
		}
	}

/**
 * Generates a unique, non-random DOM ID for an object, based on the object type and the target URL.
 *
 * @param string $object Type of object, i.e. 'form' or 'link'
 * @param string $url The object's target URL
 * @return string
 */
	public function uuid($object, $url) {
		$c = 1;
		$url = Router::url($url);
		$hash = $object . substr(md5($object . $url), 0, 10);
		while (in_array($hash, $this->uuids)) {
			$hash = $object . substr(md5($object . $url . $c), 0, 10);
			$c++;
		}
		$this->uuids[] = $hash;
		return $hash;
	}

/**
 * Magic accessor for helpers.
 *
 * @param string $name Name of the attribute to get.
 * @return mixed
 */
	public function __get($name) {
		if (isset($this->Helpers->{$name})) {
			$this->{$name} = $this->Helpers->{$name};
			return $this->Helpers->{$name};
		}
		return $this->{$name};
	}

/**
 * Magic accessor for deprecated attributes.
 *
 * @param string $name Name of the attribute to set.
 * @param mixed $value Value of the attribute to set.
 * @return void
 */
	public function __set($name, $value) {
		$this->{$name} = $value;
	}

/**
 * Magic isset check for deprecated attributes.
 *
 * @param string $name Name of the attribute to check.
 * @return boolean
 */
	public function __isset($name) {
		if (isset($this->{$name})) {
			return true;
		}
		return false;
	}

/**
 * Interact with the HelperRegistry to load all the helpers.
 *
 * @return void
 */
	public function loadHelpers() {
		$helpers = $this->Helpers->normalizeArray($this->helpers);
		foreach ($helpers as $properties) {
			list(, $class) = pluginSplit($properties['class']);
			$this->{$class} = $this->Helpers->load($properties['class'], $properties['settings']);
		}
	}

/**
 * Renders and returns output for given view filename with its
 * array of data. Handles parent/extended views.
 *
 * @param string $viewFile Filename of the view
 * @param array $data Data to include in rendered view. If empty the current View::$viewVars will be used.
 * @return string Rendered output
 * @throws \Cake\Error\Exception when a block is left open.
 */
	protected function _render($viewFile, $data = array()) {
		if (empty($data)) {
			$data = $this->viewVars;
		}
		$this->_current = $viewFile;
		$initialBlocks = count($this->Blocks->unclosed());

		$eventManager = $this->getEventManager();
		$beforeEvent = new Event('View.beforeRenderFile', $this, array($viewFile));

		$eventManager->dispatch($beforeEvent);
		$content = $this->_evaluate($viewFile, $data);

		$afterEvent = new Event('View.afterRenderFile', $this, array($viewFile, $content));
		$eventManager->dispatch($afterEvent);
		if (isset($afterEvent->result)) {
			$content = $afterEvent->result;
		}

		if (isset($this->_parents[$viewFile])) {
			$this->_stack[] = $this->fetch('content');
			$this->assign('content', $content);

			$content = $this->_render($this->_parents[$viewFile]);
			$this->assign('content', array_pop($this->_stack));
		}

		$remainingBlocks = count($this->Blocks->unclosed());

		if ($initialBlocks !== $remainingBlocks) {
			throw new Error\Exception(sprintf('The "%s" block was left open. Blocks are not allowed to cross files.', $this->Blocks->active()));
		}
		return $content;
	}

/**
 * Sandbox method to evaluate a template / view script in.
 *
 * @param string $viewFile Filename of the view
 * @param array $dataForView Data to include in rendered view.
 *    If empty the current View::$viewVars will be used.
 * @return string Rendered output
 */
	protected function _evaluate($viewFile, $dataForView) {
		$this->__viewFile = $viewFile;
		extract($dataForView);
		ob_start();

		include $this->__viewFile;

		unset($this->__viewFile);
		return ob_get_clean();
	}

/**
 * Loads a helper. Delegates to the `HelperRegistry::load()` to load the helper
 *
 * @param string $helperName Name of the helper to load.
 * @param array $settings Settings for the helper
 * @return Helper a constructed helper object.
 * @see HelperRegistry::load()
 */
	public function loadHelper($helperName, $settings = array()) {
		return $this->Helpers->load($helperName, $settings);
	}

/**
 * Returns filename of given action's template file (.ctp) as a string.
 * CamelCased action names will be under_scored! This means that you can have
 * LongActionNames that refer to long_action_names.ctp views.
 *
 * @param string $name Controller action to find template filename for
 * @return string Template filename
 * @throws \Cake\Error\MissingViewException when a view file could not be found.
 */
	protected function _getViewFileName($name = null) {
		$subDir = null;

		if ($this->subDir !== null) {
			$subDir = $this->subDir . DS;
		}

		if ($name === null) {
			$name = $this->view;
		}
		$name = str_replace('/', DS, $name);
		list($plugin, $name) = $this->pluginSplit($name);

		if (strpos($name, DS) === false && $name[0] !== '.') {
			$name = $this->viewPath . DS . $subDir . Inflector::underscore($name);
		} elseif (strpos($name, DS) !== false) {
			if ($name[0] === DS || $name[1] === ':') {
				if (is_file($name)) {
					return $name;
				}
				$name = trim($name, DS);
			} elseif ($name[0] === '.') {
				$name = substr($name, 3);
			} elseif (!$plugin || $this->viewPath !== $this->name) {
				$name = $this->viewPath . DS . $subDir . $name;
			}
		}
		$paths = $this->_paths($plugin);
		$exts = $this->_getExtensions();
		foreach ($exts as $ext) {
			foreach ($paths as $path) {
				if (file_exists($path . $name . $ext)) {
					return $path . $name . $ext;
				}
			}
		}
		$defaultPath = $paths[0];

		if ($this->plugin) {
			$pluginPaths = App::objects('Plugin');
			foreach ($paths as $path) {
				if (strpos($path, $pluginPaths[0]) === 0) {
					$defaultPath = $path;
					break;
				}
			}
		}
		throw new Error\MissingViewException(array('file' => $defaultPath . $name . $this->_ext));
	}

/**
 * Splits a dot syntax plugin name into its plugin and filename.
 * If $name does not have a dot, then index 0 will be null.
 * It checks if the plugin is loaded, else filename will stay unchanged for filenames containing dot
 *
 * @param string $name The name you want to plugin split.
 * @param boolean $fallback If true uses the plugin set in the current Request when parsed plugin is not loaded
 * @return array Array with 2 indexes. 0 => plugin name, 1 => filename
 */
	public function pluginSplit($name, $fallback = true) {
		$plugin = null;
		list($first, $second) = pluginSplit($name);
		if (Plugin::loaded($first) === true) {
			$name = $second;
			$plugin = $first;
		}
		if (isset($this->plugin) && !$plugin && $fallback) {
			$plugin = $this->plugin;
		}
		return array($plugin, $name);
	}

/**
 * Returns layout filename for this template as a string.
 *
 * @param string $name The name of the layout to find.
 * @return string Filename for layout file (.ctp).
 * @throws \Cake\Error\MissingLayoutException when a layout cannot be located
 */
	protected function _getLayoutFileName($name = null) {
		if ($name === null) {
			$name = $this->layout;
		}
		$subDir = null;

		if ($this->layoutPath !== null) {
			$subDir = $this->layoutPath . DS;
		}
		list($plugin, $name) = $this->pluginSplit($name);
		$paths = $this->_paths($plugin);
		$file = 'Layout' . DS . $subDir . $name;

		$exts = $this->_getExtensions();
		foreach ($exts as $ext) {
			foreach ($paths as $path) {
				if (file_exists($path . $file . $ext)) {
					return $path . $file . $ext;
				}
			}
		}
		throw new Error\MissingLayoutException(array('file' => $paths[0] . $file . $this->_ext));
	}

/**
 * Get the extensions that view files can use.
 *
 * @return array Array of extensions view files use.
 */
	protected function _getExtensions() {
		$exts = array($this->_ext);
		if ($this->_ext !== '.ctp') {
			$exts[] = '.ctp';
		}
		return $exts;
	}

/**
 * Finds an element filename, returns false on failure.
 *
 * @param string $name The name of the element to find.
 * @return mixed Either a string to the element filename or false when one can't be found.
 */
	protected function _getElementFileName($name) {
		list($plugin, $name) = $this->pluginSplit($name);

		$paths = $this->_paths($plugin);
		$exts = $this->_getExtensions();
		foreach ($exts as $ext) {
			foreach ($paths as $path) {
				if (file_exists($path . 'Element' . DS . $name . $ext)) {
					return $path . 'Element' . DS . $name . $ext;
				}
			}
		}
		return false;
	}

/**
 * Return all possible paths to find view files in order
 *
 * @param string $plugin Optional plugin name to scan for view files.
 * @param boolean $cached Set to false to force a refresh of view paths. Default true.
 * @return array paths
 */
	protected function _paths($plugin = null, $cached = true) {
		if ($plugin === null && $cached === true && !empty($this->_paths)) {
			return $this->_paths;
		}
		$paths = array();
		$viewPaths = App::path('Template');
		$corePaths = App::core('Template');

		if (!empty($plugin)) {
			$count = count($viewPaths);
			for ($i = 0; $i < $count; $i++) {
				if (!in_array($viewPaths[$i], $corePaths)) {
					$paths[] = $viewPaths[$i] . 'Plugin' . DS . $plugin . DS;
				}
			}
			$paths = array_merge($paths, App::path('Template', $plugin));
		}

		$paths = array_unique(array_merge($paths, $viewPaths));
		if (!empty($this->theme)) {
			$theme = Inflector::camelize($this->theme);
			$themePaths = array();
			foreach ($paths as $path) {
				if (strpos($path, DS . 'Plugin' . DS) === false) {
					if ($plugin) {
						$themePaths[] = $path . 'Themed' . DS . $theme . DS . 'Plugin' . DS . $plugin . DS;
					}
					$themePaths[] = $path . 'Themed' . DS . $theme . DS;
				}
			}
			$paths = array_merge($themePaths, $paths);
		}
		$paths = array_merge($paths, $corePaths);
		if ($plugin !== null) {
			return $paths;
		}
		return $this->_paths = $paths;
	}

/**
 * Checks if an element is cached and returns the cached data if present
 *
 * @param string $name Element name
 * @param string $data Data
 * @param array $options Element options
 * @return string|null
 */
	protected function _elementCache($name, $data, $options) {
		$plugin = null;
		list($plugin, $name) = $this->pluginSplit($name);

		$underscored = null;
		if ($plugin) {
			$underscored = Inflector::underscore($plugin);
		}
		$keys = array_merge(array($underscored, $name), array_keys($options), array_keys($data));
		$this->elementCacheSettings = array(
			'config' => $this->elementCache,
			'key' => implode('_', $keys)
		);
		if (is_array($options['cache'])) {
			$defaults = array(
				'config' => $this->elementCache,
				'key' => $this->elementCacheSettings['key']
			);
			$this->elementCacheSettings = array_merge($defaults, $options['cache']);
		}
		$this->elementCacheSettings['key'] = 'element_' . $this->elementCacheSettings['key'];
		return Cache::read($this->elementCacheSettings['key'], $this->elementCacheSettings['config']);
	}

/**
 * Renders an element and fires the before and afterRender callbacks for it
 * and writes to the cache if a cache is used
 *
 * @param string $file Element file path
 * @param array $data Data to render
 * @param array $options Element options
 * @return string
 */
	protected function _renderElement($file, $data, $options) {
		if ($options['callbacks']) {
			$this->getEventManager()->dispatch(new Event('View.beforeRender', $this, array($file)));
		}

		$current = $this->_current;
		$restore = $this->_currentType;

		$this->_currentType = static::TYPE_ELEMENT;
		$element = $this->_render($file, array_merge($this->viewVars, $data));

		$this->_currentType = $restore;
		$this->_current = $current;

		if ($options['callbacks']) {
			$this->getEventManager()->dispatch(new Event('View.afterRender', $this, array($file, $element)));
		}
		if (isset($options['cache'])) {
			Cache::write($this->elementCacheSettings['key'], $element, $this->elementCacheSettings['config']);
		}
		return $element;
	}
}
