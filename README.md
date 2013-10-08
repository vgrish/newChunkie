newChunkie
==========

Fast and reliable parsing addition for MODX Revolution. Available on [MODX Extras](http://modx.com/extras/package/newchunkie) and via Package Management.

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
	$chunkie->prepareTemplate($i, $chunkie->getPlaceholders('whateverQueue'), 'whateverQueue');
	$i++;
}
$output = $chunkie->process('whateverQueue');
```
