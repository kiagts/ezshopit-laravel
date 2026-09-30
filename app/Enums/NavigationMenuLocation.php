<?php
namespace App\Enums;
enum NavigationMenuLocation:string { case Header='header'; case FooterShop='footer_shop'; case FooterHelp='footer_help'; case FooterSellers='footer_sellers'; case FooterCompany='footer_company'; public function label():string{return match($this){self::Header=>'Header Navigation',self::FooterShop=>'Footer — Shop',self::FooterHelp=>'Footer — Help',self::FooterSellers=>'Footer — Sellers',self::FooterCompany=>'Footer — Company'};} }
