<?php
/*
description : Access to NCBI using eSummary and eSearch
author      : Ikuo Obataya
email       : ikuo_obataya@symplus.co.jp
lastupdate  : 2008-03-22
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/
if(!defined('DOKU_INC')) die();
class ncbi{
  var $HttpClient;
  var $eSummaryURL = '';
  var $eSearchURL  = '';
  var $pubmedURL   = '';
  function ncbi()
  {
    $this->HttpClient  = new DokuHTTPClient();
    $this->eSummaryURL = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=%s&retmode=xml&id=%s';
    $this->eSearchURL  = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=%s&term=%s';
    $this->pubchemURL  = 'http://pubchem.ncbi.nlm.nih.gov/summary/summary.cgi?cid=%s&disopt=DisplayXML';   
    $this->pubmedURL   = 'http://www.ncbi.nlm.nih.gov/pubmed/%s';
  }
  /*
   * Retrieve Summary XML
   */
  function SummaryXml($db,$id){
    $url = sprintf($this->eSummaryURL,urlencode($db),urlencode($id));
    $summary = $this->HttpClient->get($url);
    if (preg_match("/error/i",$summary)){return NULL;}
    return $summary;

  }
  /*
   * Retrieve Search result
   */
  function SearchXml($db,$term){
    $result = $this->HttpClient->get(sprintf($this->eSearchURL,urlencode($db),urlencode($term)));
    if (preg_match("/error/i",$result)){return NULL;}
    return $result;
  }
  /*
   * Convert PDB ID to Structure ID
   */
  function PDBtoStructureID($pdbAcc){
    $xml = $this->SearchXml('structure',$pdbAcc);
    $ids = $this->GetSearchItems("Id",$xml);
    for ($i=0;$i<count($ids);$i++){
      $tmpXml   = $this->SummaryXML('structure',$ids[$i]);
      $tmpPdbId = $this->GetSummaryItem("PdbAcc",$tmpXml);
      if (strtolower($pdbAcc)==strtolower($tmpPdbId)){
        return $ids[$i];
      }
    }
    return 0;
  }
  function GetPubchemXml($cid){
    $xml = $this->HttpClient->get(sprintf($this->pubchemURL,$cid));
    if (preg_match("/error/i",$xml)){return NULL;}
    return $xml;
  }

  /*
   * Handle XML elements
   */
  
  function GetSummaryItem($item,&$xml){
    preg_match('/"'.$item.'"[^>]*>([^<]+)/',$xml,$m);
    return $m[1];
  }
  
  function GetSummaryItems($item,&$xml){
    preg_match_all('/"'.$item.'"[^>]*>([^<]+)/',$xml,$m);
    return $m[1];
  }
  
  function GetSearchItem($item,&$xml){
     preg_match("/<".$item.">([^<]+?)</",$xml,$m);
     return $m[1];    
  }
  
  function GetSearchItems($item,&$xml){
     preg_match_all("/<".$item.">([^<]+?)</",$xml,$m);
     return $m[1];
  }
  
}
?>