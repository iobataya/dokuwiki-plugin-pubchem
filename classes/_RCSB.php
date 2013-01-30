<?php
/*
description : RCSB utility to access RCSB PDB
author      : Ikuo Obataya
email       : ikuo_obataya@symplus.co.jp
lastupdate  : 2008-03-22
license     : GPL 2 (http://www.gnu.org/licenses/gpl.html)
*/
if(!defined('DOKU_INC')) die();
class rcsb{
  var $HttpClient;
  var $ImgURL;
  var $LinkURL;
  var $LinkFormat;
  function rcsb()
  {
    $this->HttpClient = new DokuHTTPClient();
    $this->ImgURL     = 'http://www.rcsb.org/pdb/images/%s_bio_r_500.jpg';
    $this->LinkURL    = 'http://www.rcsb.org/pdb/explore.do?structureId=%s';
    $this->LinkFormat = '<a href="http://www.rcsb.org/pdb/explore.do?structureId=%s"><span class="%s">%s</span></a>';
  }
  /*
   * Download protein image
   */
  function DownloadImage($pdbid,$path){
    $pdbid = $this->PDBformat($pdbid);
    if ($pdbid===false) return false;
    if(!@file_exists($path)){
      $downloadURL = sprintf($this->ImgURL,$pdbid);
      io_download($downloadURL,$path);
    }
    return @file_exists($path);
  }
  /*
   * Return URL of a specified protein
   */
  function ExplorerURL($pdbid){
    $pdbid = $this->PDBformat($pdbid);
    if ($pdbid===false) return false;
    return sprintf($this->LinkURL,$pdbid);
  }
  /*
   * Make a link for RCSB Explorer
   */
  function ExplorerLink($pdbid,$class="pdb_plugin_acc"){
    $pdbid = $this->PDBformat($pdbid);
    if ($pdbid===false) return false;
    $class  = urlencode($class);
    return sprintf($this->LinkFormat,$pdbid,$class,strtoupper($pdbid));
  }
  
  function PDBformat($pdbid){
    $pdbid=strtolower(urlencode($pdbid));
    if (strlen($pdbid)!=4) return false;
    return $pdbid;
  }
}
  