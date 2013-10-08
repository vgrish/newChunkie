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
 * @version 1.0
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
	 * The basepath @FILE is prefixed with.
	 * @var string $basepath
	 * @access private
	 */
	private $basepath;

	/**
	 * Uncached MODX tags are not parsed inside of newChunkie.
	 * @var string $parseLazy
	 * @access private
	 */
	private $parseLazy;

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
	 * The maximum depth of the placeholder keypath.
	 * @var int $maxdepth
	 * @access private
	 */
	private $maxdepth;

	/**
	 * newChunkie constructor
	 *
	 * @param modX &$modx A reference to the modX instance.
	 * @param array $config An array of configuration options. Optional.
	 */
	function __construct(modX &$modx, array $config = array()) {
		$this->modx = & $modx;

		$this->depth = 0;
		$this->maxdepth = (integer) $this->modx->getOption('maxdepth', $config, 4);
		if ($this->modx->getOption('useCorePath', $config, FALSE)) {
			$this->basepath = MODX_CORE_PATH . $this->modx->getOption('basepath', $config, ''); // Basepath @FILE is prefixed with.
		} else {
			$this->basepath = MODX_BASE_PATH . $this->modx->getOption('basepath', $config, ''); // Basepath @FILE is prefixed with.
		}
		$this->tpl = $this->getTemplateChunk($config['tpl']);
		$this->tplWrapper = $this->getTemplateChunk($config['tplWrapper']);
		$this->parseLazy = $this->modx->getOption('parseLazy', $config, FALSE);
		$this->queue = $this->modx->getOption('queue', $config, 'default');
		$this->placeholders = array();
		$this->templates = array();
	}

	/**
	 * Set the basepath @FILE is prefixed with.
	 *
	 * @access public
	 * @param string $basepath The basepath @FILE is prefixed with.
	 */
	public function setBasepath($basepath) {
		$this->basepath = $basepath;
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
	 * @param boolean $wrapper The new template string for rendering.
	 */
	public function setTpl($tpl, $wrapper = FALSE) {
		// mask uncached elements if parseLazy is set
		if ($this->parseLazy) {
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
		if ($this->depth > $this->maxdepth) {
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
	 * @return string The placeholders.
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
		$this->placeholders[$queue] = array();
	}

	/**
	 * Clear the templates array.
	 *
	 * @access public
	 * @param string $queue The queue name.
	 */
	public function clearTemplates($queue = '') {
		$queue = !empty($queue) ? $queue : $this->queue;
		$this->templates[$queue] = new stdClass();
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
		$keypath = explode('.', $key);
		$lastkey = array_pop($keypath);

		// fill keypath based templates array
		$current = &$this->templates[$queue];
		foreach ($keypath as $currentkey) {
			if (!$current) {
				$current = new stdClass();
				$current->templates = array();
				$current->wrapper = (!empty($this->tplWrapper)) ? $this->tplWrapper : '[[+wrapper]]';
			}
			$current = &$current->templates[$currentkey];
		}
		if (!empty($this->tpl)) {
			$current->templates[$lastkey] = $this->tpl;
			// Replace placeholders array (only full placeholder tags are replaced)
			foreach ($placeholders as $k => $v) {
				$current->templates[$lastkey] = str_replace('[[+' . $k . ']]', $v, $current->templates[$lastkey]);
			}
			// Replace remaining placeholders with key based placeholders
			$current->templates[$lastkey] = str_replace('[[+', '[[+' . $key . '.', $current->templates[$lastkey]);
		} else {
			$current->templates[$lastkey] = '';
		}
		if (!$current->wrapper) {
			$current->wrapper = (!empty($this->tplWrapper)) ? $this->tplWrapper : '[[+wrapper]]';
		}
		unset($current);
	}

	/**
	 * Recursive sort the templates object by key.
	 *
	 * @access public
	 * @param object $array The templates object to sort.
	 */
	private function templatesSortRecursive(stdClass &$object) {
		foreach ($object->templates as &$value) {
			if (is_object($value))
				$this->templatesSortRecursive($value);
		}
		ksort($object->templates);
	}

	/**
	 * Flatten the templates object by wrapping templates and concatenating keys with dots.
	 *
	 * @access public
	 * @param array $array The array to flatten.
	 * @param string $prefix Top-level prefix. Optional
	 */
	private function templatesFlattenRecursive(stdClass $object, $prefix = '') {
		$result = array();
		foreach ($object->templates as $key => $value) {
			if (is_object($value)) {
				$result[$prefix . $key] = str_replace('[[+wrapper]]', implode("\r\n", $this->templatesFlattenRecursive($value, $prefix . $key . '.')), $object->wrapper);
			} else {
				$result[$prefix . $key] = $value;
			}
		}
		return $result;
	}

	/**
	 * Process the current queue with the queue placeholders.
	 *
	 * @access public
	 * @return string Processed template.
	 */
	public function process($queue = '', $clear = TRUE) {
		$queue = !empty($queue) ? $queue : $this->queue;
		if (!empty($this->templates[$queue])) {
			// sort the templates array recursive by keys
			$this->templatesSortRecursive($this->templates[$queue]);

			// flatten keypath based templates/wrapper object
			$template = $this->templatesFlattenRecursive($this->templates[$queue]);
			$template = implode("\r\n", $template);

			// process the whole template
			$chunk = $this->modx->newObject('modChunk');
			$chunk->setCacheable(false);
			$output = $chunk->process($this->placeholders[$queue], $template);
			unset($chunk);

			// unmask uncached elements (will be parsed outside of this)
			if ($this->parseLazy) {
				$output = str_replace(array('[[¡'), array('[[!'), $output);
			}
		} else {
			$output = '';
		}
		if ($clear) {
			$this->clearPlaceholders($queue);
			$this->clearTemplates($queue);
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
					if (file_exists($this->basepath . $filename)) {
						$template = file_get_contents($this->basepath . $filename);
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