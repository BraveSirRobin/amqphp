<?php
 namespace amqphp\protocol\abstrakt; abstract class ClassFactory { protected static $Cache; final static function GetClassByIndex ($index) { foreach (static::$Cache as $cNum => $c) { if ($c[0] === $index) { return is_string($c[2]) ? (static::$Cache[$cNum][2] = new $c[2]) : $c[2]; } } } final static function GetClassByName ($name) { foreach (static::$Cache as $cNum => $c) { if ($c[1] === $name) { return is_string($c[2]) ? (static::$Cache[$cNum][2] = new $c[2]) : $c[2]; } } } final static function GetMethod ($c, $m) { $c = is_int($c) || is_numeric($c) ? self::GetClassByIndex($c) : self::GetClassByName($c); if ($c) { $m = is_int($m) || is_numeric($m) ? $c->getMethodByIndex($m) : $c->getMethodByName($m); if ($m) { return $m; } } } } 