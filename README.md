# Gdimage

PHP library for create, scaling, cropping and compare images

### Usage

#### Simple example

```php
//Cut a jpg picture to a 16: 9 format and save it as png
require '/yourpath/Gdimage.php';

$quality = 95;  //95%
gdImage::create('mypicture.jpg')
  ->adjust(16,9)  
  ->saveAsFile('mypicture.png',$quality)
;

```

#### Simple example 2

```php
//Distance of two pictures
$gdImg = gdImage::create("img1.jpg");

//Uses a 4x4 color matrix for comparison
$size = 4;
  
$distance = gdImage::create($imgPath."img2.jpg")
  ->distance($gdImg, $size)
;
```

### Documentation

http://jspit.de/tools/classdoc.php?class=Gdimage
 
### Examples and Test

http://jspit.de/check/phpcheck.class.gdimage.php

### Requirements

- PHP 7.x
