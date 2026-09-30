<?php
namespace App\Support;
use InvalidArgumentException;
final readonly class Currency {
 public function __construct(public string $code, public int $minorUnit=2) { if(!preg_match('/^[A-Z]{3}$/',$code)||$minorUnit<0||$minorUnit>4) throw new InvalidArgumentException('Invalid ISO currency metadata.'); }
 public static function from(string $code,int $minorUnit=2):self { return new self(strtoupper(trim($code)),$minorUnit); }
 public function metadata():array{return ['code'=>$this->code,'minor_unit'=>$this->minorUnit];}
}
