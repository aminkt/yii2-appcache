How install widget?
---------
Add flowing line into your `composer.json` files.

```js
"aminkt/yii2-appcache" : "@dev"
```




How use widget?
---------
Add flowing lines into your controller class.

```php
=
public function behaviors()
{
    return [
        'appcache' => [
            'class' => AppCacheFilter::className(),
            'actions' => ['index', 'view']
        ],
    ];
}
```
> In action part of configuration add actions that you want create an manifest file for it.

Then add flowing line into your `main.php` of your layout.

```php
<?php
$appCache = \aminkt\components\appcache\AppCacheFilter::getManifestFileUrl($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html <?= $appCache ? "manifest=\"$appCache\"" : '' ?> >
```