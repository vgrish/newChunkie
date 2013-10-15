<?php

/**
 * newChunkie
 *
 * Copyright 2013 by Thomas Jakobi <thomas.jakobi@partout.info>
 *
 * newChunkie is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * newChunkie is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * newChunkie; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package chunkie
 * @subpackage classfile
 * @version 1.0.2
 *
 * newChunkie Class.
 *
 * This class bases loosely on the Chunkie class idea for MODX Evolution by
 * Armand "bS" Pondman <apondman@zerobarrier.nl>
 */
class newChunkie {

	/**
	 * A reference to the modX instance
	 * @var modX $modx
	 */
	public $modx;

	/**
	 * The name of a MODX chunk for the row template (could be prefixed by
	 * @FILE, @INLINE or @CHUNK). Chunknames starting with '@FILE ' are loading
	 * a chunk from the filesystem (prefixed by $basepath). Chunknames starting
	 * with '@INLINE ' contain the template code itself.
	 *
	 * @var string $tpl
	 * @access private
	 */
	private $tpl;

	/**
	 * The name of a MODX chunk for the wrapper template (could be prefixed by
	 * @FILE, @INLINE or @CHUNK). Chunknames starting with '@FILE ' are loading
	 * a chunk from the filesystem (prefixed by $basepath). Chunknames starting
	 * with '@INLINE ' contain the template code itself.
	 *
	 * @var string $tpl
	 * @access private
	 */
	private $tplWrapper;

	/**
	 * The name of current rendering queue.
	 *
	 * @var string $queue
	 * @access private
	 */
	private $queue;

	/**
	 * The prepared templates for all rendering queues.
	 *
	 * @var array $templates
	 * @access private
	 */
	private $templates;

	/**
	 * Global options.
	 *
	 * @var array $options
	 * @access private
	 */
	private $options;

	/**
	 * A collection of all placeholders.
	 * @var array $placeholders
	 * @access private
	 */
	private $placeholders;

	/**
	 * The current depth of the placeholder keypath.
	 * @var array $depth
	 * @access private
	 */
	private $depth;

	/**
	 * Profile informations for all rendering queues.
	 *
	 * @var array $profile
	 * @access private
	 */
	private $profile;

	/**
	 * newChunkie constructor
	 *
	 * @param modX &$modx A reference to the modX instance.
	 * @param array $config An array of configuration options. Optional.
	 */
	function __construct(modX &$modx, array $config = array()) {
		$this->modx = & $modx;

		$this->depth = 0;
		if ($this->modx->getOption('useCorePath', $config, FALSE)) {
			// Basepath @FILE is prefixed with
			$this->options['basepath'] = MODX_CORE_PATH . $this->modx->getOption('basepath', $config, '');
		} else {
			// Basepath @FILE is prefixed with
			$this->options['basepath'] = MODX_BASE_PATH . $this->modx->getOption('basepath', $config, '');
		}
		$this->options['maxdepth'] = (integer) $this->modx->getOption('maxdepth', $config, 4);
		$this->options['parseLazy'] = $this->modx->getOption('parseLazy', $config, FALSE);
		$this->options['profile'] = $this->modx->getOption('profile', $config, FALSE);
		$this->tpl = $this->getTemplateChunk($config['tpl']);
		$this->tplWrapper = $this->getTemplateChunk($config['tplWrapper']);
		$this->queue = $this->modx->getOption('queue', $config, 'default');
		$this->placeholders = array();
		$this->templates = array();
		$this->profile = array();
	}

	/**
	 * Set an option.
	 *
	 * @access public
	 * @param string $key The option key.
	 * @param string $value The  option value.
	 *
	 * following option keys are valid:
	 * - basepath: The basepath @FILE is prefixed with.
	 * - maxdepth: The maximum depth of the placeholder keypath.
	 * - parseLazy: Uncached MODX tags are not parsed inside of newChunkie.
	 * - profile: profile preparing/rendering times.
	 */
	public function setOption($key, $value) {
		$this->options[$key] = $value;
	}

	/**
	 * Set current rendering queue.
	 *
	 * @access public
	 * @param string $queue The name of the queue.
	 */
	public function setQueue($queue) {
		$this->queue = $queue;
	}

	/**
	 * Get current rendering queue.
	 *
	 * @access public
	 * @return string Current rendering queue.
	 */
	public function getQueue() {
		return $this->queue;
	}

	/**
	 * Change the template for rendering.
	 *
	 * @access public
	 * @param string $tpl The new template string for rendering.
	 * @param boolean $wrapper Set wrapper template if true.
	 */
	public function setTpl($tpl, $wrapper = FALSE) {
		// Mask uncached elements if parseLazy is set
		if ($this->options['parseLazy']) {
			$tpl = str_replace('[[!', '[[¡', $tpl);
		}
		if (!$wrapper) {
			$this->tpl = $tpl;
		} else {
			$this->tplWrapper = $tpl;
		}
	}

	/**
	 * Change the wrapper template for rendering.
	 *
	 * @access public
	 * @param string $tpl The new wrapper template string for rendering.
	 */
	public function setTplWrapper($tpl) {
		$this->setTpl($tpl, TRUE);
	}

	/**
	 * Fill placeholder array with values. If $value contains a nested
	 * array the key of the subarray is prefixed to the placeholder key
	 * separated by dot sign.
	 *
	 * @access public
	 * @param string $value The value(s) the placeholder array is filled
	 * with. If $value contains an array, all elements of the array are
	 * filled into the placeholder array using key/value. If one array
	 * element contains a subarray the function will be called recursive
	 * prefixing $keypath with the key of the subarray itself.
	 * @param string $key The key $value will get in the placeholder array
	 * if it is not an array, otherwise $key will be used as $keypath.
	 * @param string $keypath The string separated by dot sign $key will
	 * be prefixed with.
	 * @param string $queue The queue name
	 */
	public function setPlaceholders($value = '', $key = '', $keypath = '', $queue = '') {
		if ($this->depth > $this->options['maxdepth']) {
			return;
		}
		$queue = ($queue != '') ? $queue : $this->queue;
		$keypath = ($keypath !== '') ? strval($keypath) . "." . $key : $key;
		if (is_array($value)) {
			$this->depth++;
			foreach ($value as $subkey => $subval) {
				$this->setPlaceholders($subval, $subkey, $keypath, $queue);
			}
			$this->depth--;
		} else {
			$this->placeholders[$queue][$keypath] = $value;
		}
	}

	/**
	 * Add one value to the placeholder array with its key.
	 *
	 * @access public
	 * @param string $key The key for the placeholder added.
	 * @param string $value The value for the placeholder added.
	 * @param string $queue The queue name.
	 */
	public function setPlaceholder($key, $value, $queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		if (is_array($value)) {
			$this->depth++;
			foreach ($value as $subkey => $subval) {
				$this->setPlaceholders($subval, $subkey, $key, $queue);
			}
			$this->depth--;
		} else {
			$this->placeholders[$queue][$key] = $value;
		}
	}

	/**
	 * Get the placeholder array.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 * @return array The placeholders.
	 */
	public function getPlaceholders($queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		return $this->placeholders[$queue];
	}

	/**
	 * Get a placeholder value by key.
	 *
	 * @access public
	 * @param string $key The key for the returned placeholder.
	 * @param string $queue The queue name.
	 * @return string The placeholder.
	 */
	public function getPlaceholder($key, $queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		return $this->placeholders[$queue][$key];
	}

	/**
	 * Clear the placeholder array.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 */
	public function clearPlaceholders($queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		unset($this->placeholders[$queue]);
	}

	/**
	 * Get the templates array.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 * @return array The placeholders.
	 */
	public function getTemplates($queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		return $this->templates[$queue];
	}

	/**
	 * Clear the templates array.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 */
	public function clearTemplates($queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		unset($this->templates[$queue]);
	}

	/**
	 * Prepare the current template with key based placeholders. Replace
	 * placeholders array (only full placeholder tags are replaced - tags with
	 * modifiers remaining untouched - these were processed in $this->process)
	 * later.
	 *
	 * @access public
	 * @param string $key The key to prepend to the placeholder names.
	 * @param string $queue The queue name.
	 */
	public function prepareTemplate($key, array $placeholders = array(), $queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		if ($this->options['profile']) {
			$this->profile[$queue]['prepare'] = isset($this->profile[$queue]['prepare']) ? $this->profile[$queue]['prepare'] : 0;
			$start = microtime(TRUE);
		}
		$keypath = explode('.', $key);

		// Fill keypath based templates array
		if (!isset($this->templates[$queue])) {
			$this->templates[$queue] = new stdClass();
			$this->templates[$queue]->templates = array();
			$this->templates[$queue]->wrapper = (!empty($this->tplWrapper)) ? $this->tplWrapper : '[[+wrapper]]';
		}
		$current = &$this->templates[$queue];

		// Prepare default templates
		$currentkeypath = '';
		foreach ($keypath as $currentkey) {
			$currentkeypath .= $currentkey . '.';
			if (!isset($current->templates[$currentkey])) {
				$current->templates[$currentkey] = new stdClass();
				$current->templates[$currentkey]->templates = array();
				$current->templates[$currentkey]->wrapper = (!empty($this->tplWrapper)) ? $this->tplWrapper : '[[+wrapper]]';
				$current->templates[$currentkey]->template = '[[+' . trim($currentkeypath, '.') . ']]';
			}
			$current = &$current->templates[$currentkey];
		}
		if (!empty($this->tpl)) {
			// Set curent template
			$current->template = $this->tpl;
			// Replace placeholders array (only full placeholder tags are replaced)
			if (empty($placeholders)) {
				$placeholders = $this->getPlaceholders($queue);
				foreach ($placeholders as $k => $v) {
					$k = str_replace($key . '.', '', $k);
					$current->template = str_replace('[[+' . $k . ']]', $v, $current->template);
				}
			} else {
				foreach ($placeholders as $k => $v) {
					$current->template = str_replace('[[+' . $k . ']]', $v, $current->template);
				}
			}
			// Replace remaining placeholders with key based placeholders
			$current->template = str_replace('[[+', '[[+' . $key . '.', $current->template);
		} else {
			$current->template = '';
		}
		if (!$current->wrapper) {
			$current->wrapper = (!empty($this->tplWrapper)) ? $this->tplWrapper : '[[+wrapper]]';
		}
		unset($current);
		if ($this->options['profile']) {
			$end = microtime(TRUE);
			$this->profile[$queue]['prepare'] += $end - $start;
		}
	}

	/**
	 * Recursive sort the templates object by key.
	 *
	 * @access public
	 * @param object $array The templates object to sort.
	 */
	private function templatesSortRecursive(stdClass &$object) {
		foreach ($object->templates as &$value) {
			if (is_object($value)) {
				$this->templatesSortRecursive($value);
			}
		}
		ksort($object->templates);
	}

	/**
	 * Join the templates object by recursive wrapping templates.
	 *
	 * @access public
	 * @param array $array The array to flatten.
	 * @param string $prefix Top-level prefix.
	 * @param string $outputSeparator Separator between two joined elements.
	 */
	private function templatesJoinRecursive(stdClass $object, $prefix = '', $outputSeparator = "\r\n") {
		if (!empty($object->templates)) {
			$flat = array();
			foreach ($object->templates as $key => $value) {
				$flat = array_merge($flat, $this->templatesJoinRecursive($value, $prefix . $key . '.', $outputSeparator));
			}
			if ($prefix) {
				$return = array(trim($prefix, '.') => str_replace('[[+wrapper]]', str_replace('[[+' . trim($prefix, '.') . ']]', implode($outputSeparator, $flat), $object->template), $object->wrapper));
				foreach ($flat as $key => $value) {
					$return = str_replace('[[+' . $key . ']]', $value, $return);
				}
			}
		} else {
			$return = array(trim($prefix, '.') => $object->template);
		}
		return $return;
	}

	/**
	 * Get profiling value.
	 *
	 * @access public
	 * @param string $type The profiling type.
	 * @param string $queue The queue name.
	 * @return array The profiling value.
	 *
	 * following profiling types are valid:
	 * - prepare: Time for preparing the templates object.
	 * - render: Time for rendering the templates object.
	 */
	public function getProfile($type, $queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		$output = $this->profile[$queue][$type];
		$this->profile[$queue][$type] = 0;
		return $output;
	}

	/**
	 * Process the current queue with the queue placeholders.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 * @param string $outputSeparator Separator between two joined elements.
	 * @param boolean $clear Clear queue after process.
	 * @return string Processed template.
	 */
	public function process($queue = '', $outputSeparator = "\r\n", $clear = TRUE) {
		$queue = !empty($queue) ? $queue : $this->queue;
		if ($this->options['profile']) {
			$this->profile[$queue]['render'] = isset($this->profile[$queue]['render']) ? $this->profile[$queue]['render'] : 0;
			$start = microtime(TRUE);
		}
		if (!empty($this->templates[$queue])) {
			// Recursive join templates object
			$templates = array();
			foreach ($this->templates[$queue]->templates as $key => $value) {
				$templates = array_merge($templates, $this->templatesJoinRecursive($value, $key . '.', $outputSeparator));
			}
			$template = implode($outputSeparator, $templates);

			// Process the whole template
			$chunk = $this->modx->newObject('modChunk', array('name' => '{tmp}-' . uniqid()));
			$chunk->setCacheable(false);
			$output = $chunk->process($this->placeholders[$queue], $template);
			unset($chunk);

			// Unmask uncached elements (will be parsed outside of this)
			if ($this->options['parseLazy']) {
				$output = str_replace(array('[[¡'), array('[[!'), $output);
			}
		} else {
			$output = '';
		}
		if ($clear) {
			$this->clearPlaceholders($queue);
			$this->clearTemplates($queue);
		}
		if ($this->options['profile']) {
			$end = microtime(TRUE);
			$this->profile[$queue]['render'] += $end - $start;
		}
		return $output;
	}

	/**
	 * Get a template chunk. All chunks retrieved by this function are
	 * cached in $modx->chunkieCache for later reusage.
	 *
	 * @access public
	 * @param string $tpl The name of a MODX chunk (could be prefixed by
	 * @FILE, @INLINE or @CHUNK). Chunknames starting with '@FILE' are
	 * loading a chunk from the filesystem (prefixed by $basepath).
	 * Chunknames starting with '@INLINE' contain the template code itself.
	 * @return string The template chunk.
	 */
	public function getTemplateChunk($tpl) {
		switch (TRUE) {
			case (substr($tpl, 0, 5) == "@FILE"):
				$filename = trim(substr($tpl, 5), ' :');
				if (!isset($this->modx->chunkieCache['@FILE'])) {
					$this->modx->chunkieCache['@FILE'] = array();
				}
				if (!array_key_exists($filename, $this->modx->chunkieCache['@FILE'])) {
					if (file_exists($this->options['basepath'] . $filename)) {
						$template = file_get_contents($this->options['basepath'] . $filename);
					}
					$this->modx->chunkieCache['@FILE'][$filename] = $template;
				} else {
					$template = $this->modx->chunkieCache['@FILE'][$filename];
				}
				break;
			case (substr($tpl, 0, 7) == "@INLINE"):
				$template = trim(substr($tpl, 7), ' :');
				break;
			default:
				if (substr($tpl, 0, 6) == "@CHUNK") {
					$chunkname = trim(substr($tpl, 6), ' :');
				} else {
					$chunkname = $tpl;
					if (empty($chunkname)) {
						return '';
					}
				}
				if (!isset($this->modx->chunkieCache['@CHUNK'])) {
					$this->modx->chunkieCache['@CHUNK'] = array();
				}
				if (!array_key_exists($chunkname, $this->modx->chunkieCache['@CHUNK'])) {
					$chunk = $this->modx->getObject('modChunk', array('name' => $chunkname));
					if ($chunk) {
						$this->modx->chunkieCache['@CHUNK'][$chunkname] = $chunk->getContent();
					} else {
						$this->modx->chunkieCache['@CHUNK'][$chunkname] = FALSE;
					}
				}
				$template = $this->modx->chunkieCache['@CHUNK'][$chunkname];
				break;
		}
		return $template;
	}

}

?>
