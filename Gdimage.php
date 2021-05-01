<?php
/**
.---------------------------------------------------------------------------.
|  class Gdimage                                                            |
|   Version: 1.6                                                            |
|      Date: 2021-04-29                                                     |
| ------------------------------------------------------------------------- |
| Copyright © 2016..2021 jspit                                              |
' ------------------------------------------------------------------------- '
|  class use GD image functions                                             |
|  PHP >= 7.0                                                               |
|                                                                           |
' ------------------------------------------------------------------------- '
*/
class Gdimage {
 
  const version = '1.6';
  
  public $img = null;
  private $size = null;
  private $exifData = null;
    
 /* 
  * create a instance 
  * $imageFileName: string Filename or gd-resource
  */
  public function __construct($imageFileName = Null){
    if(is_string($imageFileName)) {
      $imagesize = getimagesize($imageFileName);
      if(!is_array($imagesize)) {
        throw new Exception("constructor parameter of class ".__CLASS__." must be a jpg-, png- or gif-Image");
      }
      $this->size = $imagesize;
      //exif exists?
      if(function_exists("exif_read_data")) {
        $exif = @exif_read_data($imageFileName);
        if($exif) $this->exifData = $exif;
      }
      
      $mime = $imagesize['mime'];
      if(preg_match('#^image/p?jpe?g$#i',$mime)) $fkt = 'imageCreateFromJpeg';
      elseif(preg_match('#^image/png$#i',$mime)) $fkt = 'imageCreateFromPng'; 
      elseif(preg_match('#^image/gif$#i',$mime)) $fkt = 'imageCreateFromGif'; 
      else {
        throw new Exception(
          "constructor parameter '".
          (string)$imageFileName. "'of class ".__CLASS__." not supported"
        );
      }
      $this->img = $fkt($imageFileName);
      if($this->img == false) {
        throw new Exception(
          "constructor parameter '".
          (string)$imageFileName. "'of class ".__CLASS__." is a invalid file"
        );
      }
    }
    elseif(is_resource($imageFileName) AND get_resource_type($imageFileName) == "gd") {
      //GD-Resource
      $this->img = $imageFileName;
      $this->size = false; //extern gd-resource      
    }
  }
  
 
 /*
  * function returns a new GdImage or Bool false if Error
  * $imageFileName: string Filename
  */ 
  public static function create($imageFileName) {
    try{
      $GdImageObj = new self($imageFileName);
    }
    catch (Exception $e) {
      $GdImageObj = false;
    }
    return $GdImageObj;
  }
  
 /*
  * adjust orientation
  */
  public function adjustOrientation(){
    if(empty($this->exifData) OR !isset($this->exifData['Orientation'])){
      return $this;
    }
    $ort = $this->exifData['Orientation'];
    $deg = 0;
    if ($ort == 5 OR $ort == 6) $deg = 270;
    if ($ort == 3 OR $ort == 4) $deg = 180;
    if ($ort == 7 OR $ort == 8) $deg = 90;
    if($deg) $this->img = imagerotate($this->img, $deg, null);
    if ($ort == 2 || $ort == 5 || $ort == 4 || $ort == 7){
      imageflip($this->img, IMG_FLIP_HORIZONTAL);  
    }
    return $this;
  }
  
 /*
  * @return resource of GD
  */  
  public function getResource(){
    return $this->img;
  }

 /*
  * @return array with exif-Infos
  * @return false if exif_read_data() not aviable
  */  
  public function getExif(){
    return $this->exifData 
      ? $this->exifData
      : false      
    ;
  }

 /*
  * @return new resource of GD with a copy
  */  
  public function getResourceCopy(){
    return imagecrop(
      $this->img,
      array('x'=>0,'y'=>0,'width'=>imagesx($this->img),'height'=>imagesy($this->img))
    );
  }
  
 /*
  * return true if number of colors > 256
  * use imageistruecolor($image)
  */
  public function isTrueColor() {
    //return 0 if number too high
    $numberOfColors = imagecolorstotal($this->img); 
    return $numberOfColors == 0 OR $numberOfColors > 256;
  }

 /*
  * Returns the mixed color from all indexes of the colors of the image
  * @param $size , resulution = size * size, default 1
  * @return int color for size=1 or array['x.y' => color, ..] 
  */
  public function getMixColor($size = 1, $border = 0) {
    return self::getMixColorFromGd($this->img, $size, $border);
  }

 /*
  * return resolution as string (format width x height)
  */
  public function getResolution() {
     return imagesx($this->img).' x '.imagesy($this->img);
  }


 /*
  * save the resource as file
  * @param $imageFileName: string Filename
  * @param $quality: 0..100 (%)
  */  
  public function saveAsFile($imageFileName, $quality = null){
    $pathInfo = pathinfo($imageFileName);
    $extension = $pathInfo['extension'];
    if($extension == "") return false;
    if(preg_match('#^png$#i',$extension)) {
      //PNG-file
      if($quality === null) {
        $saveOk = imagepng($this->img,$imageFileName);
      }
      else {
        //png kompression 0=none,9 = max
        $quality = max(min((int)$quality,100),0);
        $pngKompression = (int)((100-$quality)*0.09);
        $saveOk = imagepng($this->img,$imageFileName,$pngKompression);
      }
    }
    elseif(preg_match('#^gif$#i',$extension)) {
      //GIF-File
      $saveOk = imagegif($this->img,$imageFileName);
    }
    elseif(preg_match('#^p?jpe?g$#i',$extension)) {
      //JPG-File
      if($quality === null) {
        $saveOk = imagejpeg($this->img,$imageFileName);
      }
      else {
        //jpeg quality 0..100
        $quality = max(min((int)$quality,100),0);
        $saveOk = imagejpeg($this->img,$imageFileName,$quality);
      }
    }
    else $saveOk = false;
    
    return $saveOk;
  }
  
 /*
  * save the resource as binäry String
  * @param $quality int: 0..100 (%), 100 = uncompressed , -1 default value
  * @param optional $gdResource use if set
  * @return string or false if error
  */  
  public function saveAsString($quality = 100, $gdResource = null){
    if($gdResource === null) $gdResource = $this->img;
    $pngQuality = (int)((100-$quality)*0.09);
    ob_start();
    $ok = imagepng($gdResource, NULL, $pngQuality);
    if($ok === true) return ob_get_clean();
    ob_end_clean();
    return false;
  }

 /*
  * @return this object of GdImage or false if Error
  * @param x: width px
  * @param y: height px
  * @param scaling false/true or a factor (float)
  */  
  public function adjust($x,$y,$scaling = false) {
    if(!is_resource($this->img) OR get_resource_type($this->img) != "gd") return false;
    $srcx = $xCut = imagesx($this->img );
    $srcy = $yCut = imagesy($this->img );
    if($srcx < 1 OR $srcy < 1) return false;
    $xstart = $ystart = 0;
    if($x > 0 AND $y > 0) {
      $dstRelation = $x/$y;
      $srcRelation = $srcx/$srcy;
      if($srcRelation > $dstRelation) {
        //reduce x
        $xCut = $srcy * $dstRelation;
        $xstart = (int)round(($srcx - $xCut)/2); 
        $xCut = (int)round($xCut);
      }
      elseif($srcRelation < $dstRelation) {
        //reduce y
        $yCut = $srcx / $dstRelation;
        $ystart = (int)round(($srcy - $yCut)/2);
        $yCut = (int)round($yCut);    
      }
    }
    if(is_numeric($scaling) AND $scaling > 0) {
      //scaling is a factor
      $dstX = $scaling * $xCut;
      $dstY = $scaling * $yCut;
    }
    else {
      if($x < 1) $x = $srcx;
      if($y < 1) $y = $srcy;
      $dstX = $scaling ? $x : $xCut;
      $dstY = $scaling ? $y : $yCut;
    }
    
    $dstImg = imagecreatetruecolor($dstX,$dstY);
    imagealphablending($dstImg, false );
    imagesavealpha($dstImg, true );

    $imOk = imagecopyresampled($dstImg,$this->img ,
      0,0,              //dst_x, dst_y
      $xstart,$ystart,  // src_x, src_y
      $dstX,$dstY,      //$xCut,$yCut,     // dst_w, dst_h 
      $xCut,$yCut       // src_w, src_h
    );
    if(!$imOk) return false;
    if($this->size) {
      //only if resource create intern
      imagedestroy($this->img);
    }
    $this->img = $dstImg;
    $this->size[0] = $dstX; 
    $this->size[1] = $dstY;
    $this->size[3] = 'width="'.$dstX.'" height="'.$dstY.'"';     
    return $this;  
  }

 /*
  * Write a line of text with TrueType fonts in the image
  * textArr array
  * x int x-Position
  * y int y-Posiotion
  * font string The path to the TrueType font you wish to use.
  * fontSize int The font size
  * textColor The color index
  *
  * return new x position int
  */
  public function ttfTextLine(array $textArr, $x, $y, $font,$fontSize,$textColor)
  {
    foreach($textArr as $curr){
      if(isset($curr['font'])){
        $curFont = $curr['font'];
      }
      else {
        $curFont = $font;
      }

      if(isset($curr['text'])){
        $curText = $curr['text']; 
        $textWidth = self::textWidth($curText,$curFont,$fontSize);
      }
      else {
        $curText = "";
        $textWidth = 0;
      }
      if(isset($curr['width'])) $width = $curr['width'];
      else $width = $textWidth;
      
      $xText = 0;
      if(isset($curr['align'])){
        if($curr['align'] == 'right') $xText = $width - $textWidth;
        elseif($curr['align'] == 'middle') $xText = (int)(($width - $textWidth)/2);
      }
      ImageTTFText($this->img, $fontSize, 0, $x+$xText, $y, $textColor, $curFont, $curText);
      $x += $width + 2; 
    }
    
    return $x;
  }

 /*
  * get the width of text
  * text string the text
  * fontPath string path and filename .ttf
  * fontSize int
  */
  public static function textWidth($text, $fontPath, $fontSize)
  {
    $box = imagettfbbox($fontSize,0,$fontPath,$text);
    $xKords = array($box[0], $box[2], $box[4], $box[6]);
    return max($xKords) - min($xKords); 
  }
  
 /*
  * get Array with coordinates x,y from first different pixel
  * @param resource $gdImage
  * @return array("x" => $x, "y" => $y) first diff or false if equal
  */
  public function firstDiffPos($gdImage){
    $img = $this->img;
    $width = imagesx($img);
    if($width !== imagesx($gdImage)) return false;
    $height = imagesy($img);
    if($height !== imagesy($gdImage)) return false;
    for($x=0; $x < $width; $x++){
      for($y=0; $y < $height; $y++){
        if(imagecolorat($img, $x, $y) != imagecolorat($gdImage, $x, $y)) {
          return array("x" => $x,"y" => $y);
        }
      }
    }
    return false;
  }

 /*
  * det color-distance to $gdImage
  * @param $gdImage or object
  * @param optional int $size resulution = $size * $size
  * @param optional int $border : the hidden border.
  * @return float distance
  */
  public function distance($gdImage, $size = 1, $border = 0){
    if($gdImage instanceof self){
      $gdImage = $gdImage->img;
    }
    return self::gdDistance($this->img,$gdImage, $size, $border);
  }
  
 /*
  * check if current image equal $gdImage
  * @param $gdImage or object
  * @param optional $eps compare limit  
  * @param optional $size resulution = $size * $size
  * @return true or false
  */
  public function isEqual($gdImage, $eps = 0, $size = 5){
    $dist = $this->distance($gdImage,$size);
    return $dist <= $eps;
  }
  
 /*
  * return array ['red' => ,'green' => ,'blue' => , 'alpha' => ] 
  * @param int $color
  */
  public static function colorToArray($color){
    $rgba['red'] = $color & 0xff;
    $rgba['green'] = $color >> 8 & 0xff;
    $rgba['blue'] = $color  >> 16 & 0xff;
    $rgba['alpha'] = $color >> 24 & 0xff;
    return $rgba;
  }

 /*
  * mean square deviation of two GD images resources
  * @param $img1 gd image
  * @param $img2 gd image
  * @param $size size matrix >= 1
  * @param $border default 0
  * @return float >= 0.0
  */
  public static function gdDistance($img1, $img2, $size = 1, $border = 0){
    $colorsImg1 = self::getMixColorFromGd($img1,$size, $border);
    $colorsImg2 = self::getMixColorFromGd($img2,$size, $border);
    $dist = self::l2dist($colorsImg1, $colorsImg2,$size);
    return $dist;
  } 
  
 /*
  * get mean square deviation of two color arrays
  * @param array $colorsImg1
  * @param array $colorsImg2
  * return float L2 distance
  */
  public static function l2dist($colorsImg1,$colorsImg2,$size){
    $dist = 0;
    foreach($colorsImg1 as $yx => $color1){
      $rgb1 = self::colorToArray($color1);
      $rgb2 = self::colorToArray($colorsImg2[$yx]);
      foreach($rgb1 as $color => $value){
        $diff = $rgb2[$color] - $value;
        $dist += $diff * $diff;
      }
    }
    return sqrt($dist/($size * $size * 4));
  }

 /*
  * get mean L1 deviation of two color arrays
  * @param array $colorsImg1
  * @param array $colorsImg2
  * return float L1 distance
  */
  public static function l1dist($colorsImg1,$colorsImg2,$size){
    $dist = 0;
    foreach($colorsImg1 as $yx => $color1){
      $rgb1 = self::colorToArray($color1);
      $rgb2 = self::colorToArray($colorsImg2[$yx]);
      foreach($rgb1 as $color => $value){
        $diff = $rgb2[$color] - $value;
        $dist += abs($diff); 
      }
    }
    return $dist/($size * $size * 4);
  }


 /*
  * return int 0..255
  */
  public static function colorMaxDistance($color1, $color2){
    $rgba1 = self::colorToArray($color1);
    $rgba2 = self::colorToArray($color2);
    $max = 0;
    foreach($rgba1 as $key => $val){
      $diff = abs($rgba2[$key] - $val);
      $max = max($diff,$max); 
    }
    return $max;
  }

 /*
  * Copy and Resize $img. Return a copy with size $x, $y
  * @param $img image GD resource
  * @param int $x size X
  * @param int $y size Y
  * @return GD resource 
  */
  public static function resizeCopy($img, $x, $y = null) {
    if($y < 1) $y = $x;
    $thumb=imagecreatetruecolor($x,$y);
    imagealphablending($thumb, false );
    imagesavealpha($thumb, true );
    imagecopyresampled($thumb,$img,0,0,0,0,$x,$y,imagesx($img),imagesy($img));
    return $thumb;
  }
 

 /*
  * Returns the mixed color from all indexes of the colors of the image
  * @param $img image GD resource
  * @param $size , resulution = size * size, default 1
  * @param $border ruduce image border, default 0 
  * @return array['y.x' => color, ..] 
  */
  public static function getMixColorFromGd($img, $size = 1, $border = 0) {
    $size += 2 * $border;
    $thumb = self::resizeCopy($img,$size,$size);
    $colors = [];
    for($y=$border;$y<$size-$border;$y++){
      for($x=$border;$x<$size-$border;$x++){
        $colors[$y.".".$x] = imagecolorat($thumb,$x,$y);
      }
    }
    return $colors;
  }

 /* 
  * rgb to hsv
  * @param int $r 0-255 red
  * @param int $g 0-255 green
  * @param int $b 0-255 blue
  */
  public static function rgb2hsv($r, $g, $b){
    $r /= 255;
    $g /= 255;
    $b /= 255;
    $maxRGB = max($r, $r, $b);
    $minRGB = min($r, $r, $b);
    $chroma = $maxRGB - $minRGB;
    $computedV = 100 * $maxRGB;
    if($chroma == 0) return [0,0,$computedV];
    $computedS = 100 * ($chroma / $maxRGB);
    if($r == $minRGB) $h = 3 - (($g - $b) / $chroma);
    elseif ($b == $minRGB) $h = 1 - (($r - $g) / $chroma);
    else $h = 5 - (($b - $r) / $chroma);
    return [60 * $h, $computedS, $computedV];
  }


}
