<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_pubchem extends DokuWiki_Syntax_Plugin {
  var $ncbi;
  var $imgCache;
  var $xmlCache;
  var $summaryURL='';
  var $downloadURL='';
  var $chemProperty = array();

  function syntax_plugin_pubchem(){
    $this->name = pubchem;
    if (!class_exists('plugin_cache'))
        @require_once(DOKU_PLUGIN.'pubmed/classes/cache.php');
    if (!class_exists('rcsb')||!class_exists('ncbi')||!class_exists('xml'))
        @require_once(DOKU_PLUGIN.'pubmed/classes/sciencedb.php');
    $this->ncbi        = new ncbi();
    $this->imgCache    = new plugin_cache($this->name,'',"png");
    $this->xmlCache    = new plugin_cache($this->name,'',"xml.gz");

    $this->summaryURL  = 'http://pubchem.ncbi.nlm.nih.gov/summary/summary.cgi?cid=';
    $this->downloadURL = 'http://pubchem.ncbi.nlm.nih.gov/image/imgsrv.fcgi?t=%s&cid=%s';
  }
  function getType(){ return 'substition'; }
  function getSort(){ return 159; }

  // Connect
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('\{\{pubchem>[^}]*\}\}',$mode,'plugin_pubchem');
  }

  // Handling lexer
  function handle($match, $state, $pos, &$handler){
    $match = substr($match,10,-2);
    return array($state,explode(':',$match));
  }

/**
  * Render PubChem image and link
  */
  function render($mode, &$renderer, $data){
      if ($mode!='xhtml')
      return false;

    list($state, $match) = $data;
    list($cmd,$cid) = $match;
    $cmd = strtolower($cmd);

    if ($cmd=="small" || $cmd=="large"){
      if (!is_numeric($cid)){
        $renderer->doc .= sprintf($this->getLang('pubchem_invalid_cid'),$cid);
        return false;
      }
      global $conf;
      $mode = ($cmd=="small")?"s":"l";
      $id = $cid.$mode;

      $filename = $this->imgCache->GetMediaPath($id);
      if(is_readable($filename)){
        $renderer->doc .= $this->getImageHtml($cid,$mode);
      }else{
        $url = sprintf($this->downloadURL,$mode,$cid);
        io_download($url,$filename);
        $renderer->doc .= $this->getImageHtml($cid,$mode);
      }
      return true;
    }else if($cmd=="link"||$cmd=="summaryxml"||$cmd=="iupac"||$cmd=="smiles"||
             $cmd=="formula"||$cmd=="template"){
      if (!is_numeric($cid)){
        $renderer->doc .= sprintf($this->getLang('pubchem_invalid_cid'),$cid);
        return false;
      }
      switch($cmd){
        case 'link':
          $renderer->doc .= '<a target=_blank ';
          $renderer->doc .= 'href="'.$this->summaryURL.$cid.'" title="Go to PubChem site">';
          $renderer->doc .= '<div class="pubchem_link">&nbsp;</div></a>'.NL;
          return true;

        case 'summaryxml':
          $xml = $this->getPubchemXml($cid);
          if ($xml===false){return true;}
          $renderer->doc .= '<pre>'.htmlspecialchars($xml).'</pre>';
          return true;

        case 'iupac':
          if (empty($this->chemProperty[$cid]['iupac'])){
            $this->getProperties($cid);
          }
          $renderer->doc .= $this->chemProperty[$cid]['iupac'];
          return true;
        case 'formula':
          if (empty($this->chemProperty[$cid]['iupac'])){
            $this->getProperties($cid);
          }
          $renderer->doc.=$this->chemProperty[$cid]['formula'];
          return true;
        case 'template':
          if (empty($this->chemProperty[$cid]['iupac'])){
            $this->getProperties($cid);
          }
          $renderer->doc .= '^Name|XXX(('.$this->chemProperty[$cid]['iupac'].'))|<br/>';
          $renderer->doc .= '^Molecular Formula|&lt;chem&gt;'.$this->chemProperty[$cid]['formula'].'&lt;/chem&gt;|<br/>';
          $renderer->doc .= '^Molecular Weight|'.$this->chemProperty[$cid]['weight'].'|<br/>';
          $renderer->doc .= '^LogP|'.$this->chemProperty[$cid]['logp'].'|<br/>';
          return true;
      }
    }else{
      switch($cmd){
        case 'searchbox':
          $renderer->doc .= file_get_contents(DOKU_PLUGIN.$this->name.'/pubchem_search_box.htm').NL;
          return true;

        case 'clear':
          $this->xmlCache->ClearCache();
          $renderer->doc .= 'Cleared.';
          return true;

        case 'remove_dir':
          $this->xmlCache->RemoveDir();
          $renderer->doc .= 'Directory cleared.';
          return true;

        default:
          // Command was not found..
          $renderer->doc.='<div class="plugin_cmd">'.sprintf($this->getLang('plugin_cmd_not_found'),$cmd).'</div>';
          return true;
      }
    }
  }

/**
  * Get PubChem image description
  */
  function getImageHtml($cid,$mode){
        $tag .= '<div class="pubchem_imgbox"><a href="'.$this->summaryURL.$cid.'" target=_blank>';
        $tag .= '<img src = "'.$this->imgCache->GetMediaLink($cid.$mode).'" class="media" ';
        $tag .= 'alt="PubChem image '.$cid.'" ';
        $tag .= 'title="CID:'.$cid.'  Click to PubChem page"/></a></div>';
        return $tag;
  }
  function getProperties($cid){
    $xml = $this->getPubchemXml($cid);
    if ($xml===false){return true;}
    $x = new Xml;
    $XmlClass = $x->GetXmlObject($xml);
    $xmls = $XmlClass[0]->next;
    for($i=0;$i<count($xmls);$i++){
      if ($xmls[$i]->tag=="PC-Compound_props"){
        $props = $xmls[$i]->next;
        for($j=0;$j<count($props);$j++){
          $infodata = $props[$j]->next;

          $info_name1 = $infodata[0]->next[0]->next[0]->value;
          $info_name2 = $infodata[0]->next[0]->next[1]->value;
          $info_value = $infodata[1]->next[0]->value;

          switch($info_name1){
            case "Molecular Formula":
              $this->chemProperty[$cid]['formula']=$this->getChemFormat($info_value);
              break;
            case "IUPAC Name":
              if ($info_name2=="Preferred"||$info_name2=="Allowed"){
                $this->chemProperty[$cid]['iupac'] = $info_value;
              }

              break;
            case "Molecular Weight":
              $this->chemProperty[$cid]['weight'] = $info_value;
              break;
            case "Log P":
              $this->chemProperty[$cid]['logp'] = $info_value;
              break;
            case "SMILES":
              $this->chemProperty[$cid]['smiles'] = $info_value;
              break;
          }
        }
      }
    }
  }
 /**
  * Get PubChem XML
  */
  function getPubchemXml($cid){
    global $conf;
    $xml = $this->ncbi->GetPubchemXml($cid);
    if (!empty($xml)){
      $filepath = $this->xmlCache->GetMediaPath($cid);
      if(io_saveFile($filepath,$xml)){
        chmod($filepath,$conf['fmode']);
      }
      return $xml;
    }else{
      return false;
    }
  }
 /**
  * Get chemical format.
  */
  function getChemFormat($raw){
    $pattern = array("/[\|]?([0-9]*\+|[0-9]*\-)/","/([A-Z]|[A-Z][a-z]|\)|\])([0-9]+)/");
    $replace = array("<sup>\${1}</sup>","\${1}<sub>\${2}</sub>");
    return preg_replace($pattern,$replace,$raw);
  }
}
?>
