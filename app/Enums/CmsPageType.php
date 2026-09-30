<?php
namespace App\Enums;
enum CmsPageType:string { case Standard='standard'; case Legal='legal'; case Help='help'; case Contact='contact';
 public function label():string{return match($this){self::Standard=>'Standard',self::Legal=>'Legal',self::Help=>'Help & FAQ',self::Contact=>'Contact'};}}
