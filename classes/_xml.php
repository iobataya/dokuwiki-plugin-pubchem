<?php
/*
description : Simple Xml object class
author      : Ikuo Obataya
email       : i.obataya[at]gmail.com
lastupdate  : 2023-08-07
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/
class Xml
{
  var $tag;
  var $value;
  var $attributes;
  var $next;

  function GetXmlObject($xml){
    $Parser = xml_parser_create();
    xml_parser_set_option($Parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($Parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($Parser, $xml, $Xml_Values);
    xml_parser_free($Parser);
    $XmlClass = array();
    $LastObj = array();
    $NowObj = &$XmlClass;
    $TG = "tag";
    $VL = "value";
    $AT = "attributes";
    foreach($Xml_Values as $Xml_Key => $Xml_Value){
      $Index = count($NowObj);
      if($Xml_Value["type"] == "complete"){
        $NowObj[$Index] = new Xml;
        $NowObj[$Index]->tag = array_key_exists($TG,$Xml_Value)?$Xml_Value[$TG]:"";
        $NowObj[$Index]->value = array_key_exists($VL,$Xml_Value)?$Xml_Value[$VL]:"";
        $NowObj[$Index]->attributes = array_key_exists($AT,$Xml_Value)?$Xml_Value[$AT]:"";
      }elseif($Xml_Value["type"] == "open"){
        $NowObj[$Index] = new Xml;
        $NowObj[$Index]->tag = array_key_exists($TG,$Xml_Value)?$Xml_Value[$TG]:"";
        $NowObj[$Index]->value = array_key_exists($VL,$Xml_Value)?$Xml_Value[$VL]:"";
        $NowObj[$Index]->attributes = array_key_exists($AT,$Xml_Value)?$Xml_Value[$AT]:"";
        $NowObj[$Index]->next = array();
        $LastObj[count($LastObj)] = &$NowObj;
        $NowObj = &$NowObj[$Index]->next;
      }elseif($Xml_Value["type"] == "close"){
        $NowObj = &$LastObj[count($LastObj) - 1];
        unset($LastObj[count($LastObj) - 1]);
      }
    }
    return $XmlClass;
  }
}
