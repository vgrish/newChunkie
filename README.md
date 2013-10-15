newChunkie
==========

Fast and reliable parsing addition for MODX Revolution. Maybe later available on [MODX Extras](http://modx.com/extras/package/newchunkie) and via Package Management.

Features
-----------

MODX internal getChunk has a speed problem if you call it multiple in a snippet. This could be even worse if you iterate through nested objects. During my small tests the speed improvement with newChunkie was about the factor 3.5 with not nested getChunk calls. With one level of iteration the improvement was about the factor 4.

Requirements
-----------

* PHP 5.2 or higher

Usage
-----

newChunkie is a PHP class and can only be called from snippet/plugin code.

####Initialization:

```php
$modx->loadClass('newchunkie.newChunkie', MODX_CORE_PATH . 'components/newchunkie/model/', true, true);
$chunkie = new newChunkie($this->modx, array('parseLazy' => TRUE, 'useCorePath' => TRUE));
```

####Prepare/process the templates

```php
$c = $modx->newQuery('whateverClass');
...
$collection = $modx->getCollection('whateverClass', $c);
$chunkie->setBasepath('whateverBasepath');

// $i represents the keypath in this example
$i = 0;
$output = array();
foreach ($collection as &element) {
	$chunkie->setPlaceholders($element->toArray(), $i, '', 'whateverQueue');
	$chunkie->setTpl($chunkie->getTemplateChunk('@FILE whatever.row.html'));
	$chunkie->setTplWrapper($chunkie->getTemplateChunk('@FILE whatever.outer.html'));
	$chunkie->prepareTemplate($i, array(), 'whateverQueue');
	$i++;
}
$output = $chunkie->process('whateverQueue');
```

####Prepare/process nested templates

```php
$c = $modx->newQuery('whateverClass');
...
$collection = $modx->getCollection('whateverClass', $c);
$chunkie->setBasepath('whateverBasepath');

// $i . '.tags.' . $j represents the keypath in this example
$i = 0;
$output = array();

foreach ($collection as &$element) {
	$tags = $element->getMany('Tags');
	$currentTags = array();
	$j = 0;
	foreach ($tags as $tag) {
		$currentTag = $tag->getOne('Tag');
		$title = $currentTag->get('title');
		$chunkie->setTpl($chunkie->getTemplateChunk('@FILE whatever.tag.html'));
		$chunkie->setTplWrapper($chunkie->getTemplateChunk('@FILE whatever.tagouter.html'));
		$chunkie->setPlaceholders(array('title' => $currentTag->get('title')), $i . '.tags.' . $j, '', 'whateverQueue');
		$chunkie->prepareTemplate($i . '.tags.' . $j, array(), 'whateverQueue');
		$j++;
	}
	$chunkie->setPlaceholders($element->toArray(), $i, '', 'whateverQueue');
	$chunkie->setTpl($chunkie->getTemplateChunk('@FILE whatever.row.html'));
	$chunkie->setTplWrapper($chunkie->getTemplateChunk('@FILE whatever.outer.html'));
	$chunkie->prepareTemplate($i, array(), 'whateverQueue');
	$i++;
}

$output = $chunkie->process('whateverQueue');
```

####Prepare/process nested templates with multiple queues

```php
$c = $modx->newQuery('whateverClass');
...
$collection = $modx->getCollection('whateverClass', $c);
$chunkie->setBasepath('whateverBasepath');

// $i and $j representing the keypaths in this example
$i = 0;
$output = array();
foreach ($collection as &element) {
	$tags = $classified->getMany('Tags');
	$currentTags = array();
	$j = 0;
	foreach ($tags as $tag) {
		$currentTag = $tag->getOne('Tag');
		$title = $currentTag->get('title');
		$chunkie->setTpl($chunkie->getTemplateChunk('@FILE tag.row.html'));
	    $chunkie->setTplWrapper($chunkie->getTemplateChunk('@FILE tag.outer.html'));
		$chunkie->setPlaceholders(array('title' => $currentTag->get('title')), $j, '', 'tags');
		$chunkie->prepareTemplate($j, array(), 'tags');
		$j++;
	}
	$chunkie->setPlaceholders($chunkie->process('tags', ', '), 'tags', $i, 'whateverQueue');
	$chunkie->setPlaceholders($element->toArray(), $i, '', 'whateverQueue');
	$chunkie->setTpl($chunkie->getTemplateChunk('@FILE whatever.row.html'));
	$chunkie->setTplWrapper($chunkie->getTemplateChunk('@FILE whatever.outer.html'));
	$chunkie->prepareTemplate($i, array(), 'whateverQueue');
	$i++;
}
$output = $chunkie->process('whateverQueue');
```

