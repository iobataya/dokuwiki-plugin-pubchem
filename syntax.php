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

  function __construct(){
    $this->name = 'pubchem';
    if (!class_exists('plugin_cache'))
        @require_once(DOKU_PLUGIN.$this->name.'/classes/cache.php');
    if (!class_exists('rcsb')||!class_exists('ncbi')||!class_exists('xml'))
        @require_once(DOKU_PLUGIN.$this->name.'/classes/sciencedb.php');
    $this->ncbi        = new ncbi();
    $this->imgCache    = new plugin_cache($this->name,'',"png");
    $this->xmlCache    = new plugin_cache($this->name,'',"xml.gz");
    $this->propCache   = new plugin_cache($this->name,'prop','json');

    $this->summaryURL  = 'https://pubchem.ncbi.nlm.nih.gov/summary/summary.cgi?cid=';
    $this->downloadURL = 'https://pubchem.ncbi.nlm.nih.gov/image/imgsrv.fcgi?t=%s&cid=%s';
  }
  function getType(){ return 'substition'; }
  function getSort(){ return 159; }

  // Connect
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('\{\{pubchem>[^}]*\}\}',$mode,'plugin_pubchem');
  }

  // Handling lexer
  function handle($match, $state, $pos, Doku_Handler $handler){
    $match = substr($match,10,-2);
    return array($state,explode(':',$match));
  }

/**
  * Render PubChem image and link
  */
  function render($mode, Doku_Renderer $renderer, $data){
    if ($mode!='xhtml') return false;
    global $pubchem_cache;
    if(!is_array($pubchem_cache)) $pubchem_cache=array();

    list($state, $match) = $data;
    list($cmd,$cid) = $match;
    $cmd = strtolower($cmd);
    if(strpos($cid,'|')!==false){
      list($cid,$title)=explode('|',$cid);
    }
    // Commands without CID
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
            if(!$cid){
              $renderer->doc.='<div class="plugin_cmd">'.sprintf($this->getLang('plugin_cmd_not_found'),$cmd).'</div>';
              return true;
            }
     }
    // Commands with CID
    if (!is_numeric($cid)){
        $renderer->doc .= sprintf($this->getLang('pubchem_invalid_cid'),$cid);
        return false;
    }
    if ($this->propCache->Exists($cid)){
      $ext_props = json_decode($this->propCache->GetMediaText($cid),true);
    }else{
      $ext_props = $this->getProperties($cid);
    }
    $iupac   = array_key_exists('iupac',  $ext_props) ? $ext_props['iupac']:'';
    $formula = array_key_exists('formula',$ext_props) ? $ext_props['formula']:'';
    $mw      = array_key_exists('mw',     $ext_props) ? $ext_props['mw']:'';
    $xlogp   = array_key_exists('xlogp',  $ext_props) ? $ext_props['xlogp']:'';
    switch($cmd){
        case 'link':
            $renderer->doc .= '<a target=_blank ';
            $renderer->doc .= 'href="'.$this->summaryURL.$cid.'" title="Go to PubChem site" class="pubchem_link">'.$cid.'</a>'.NL;
            return true;
        case 'summaryxml':
            $xml = $this->getPubchemXml($cid);
            if ($xml===false){return true;}
            $renderer->doc .= '<pre>'.htmlspecialchars($xml).'</pre>';
            return true;
        case 'iupac':
            $renderer->doc.= $iupac;
            return true;
        case 'formula':
            $renderer->doc.= $formula;
            return true;
        case 'mw':
            $renderer->doc.= $mw;
            return true;
        case 'xlogp':
            $renderer->doc.= $xlogp;
            return true;
        default:
            $mode = $cmd[0]; // s or l
            $id = $cid.$mode;
            $filename = $this->imgCache->GetMediaPath($id);
            if(!is_readable($filename)){
                $url = sprintf($this->downloadURL,$mode,$cid);
                io_download($url,$filename);
            }
            if(strpos($cmd,'template')!==false){
                if (empty($pubchem_cache[$cid]['iupac'])){
                    $this->getProperties($cid);
                }
                $renderer->doc.='<div class="left" style="padding:10px;">';
                $renderer->table_open(2);
                $this->_name_row($renderer,$cid,$iupac,$title);
                $this->_row($renderer,$this->getLang('mol_formula'),$formula);
                $this->_row($renderer,$this->getLang('mol_weight'), $mw);
                $this->_row($renderer,'LogP',$xlogp);
                $renderer->table_close();
                $renderer->doc.='</div><div class="left">';
                $renderer->doc .= $this->getImageHtml($cid,$mode);
                $renderer->doc.='</div><div class="clearfix"></div>'.DOKU_LF;
            }else{
                $renderer->doc .= $this->getImageHtml($cid,$mode);
            }
            return true;
    }
    // Command was not found..
    $renderer->doc.='<div class="plugin_cmd">'.sprintf($this->getLang('plugin_cmd_not_found'),$cmd).'</div>';
    return true;
  }

/**
  * Get PubChem image description
  */
  function getImageHtml($cid,$mode){
        $tag = '<div class="pubchem_imgbox"><a href="'.$this->summaryURL.$cid.'" target=_blank>';
        $tag .= '<img src = "'.$this->imgCache->GetMediaLink($cid.$mode).'" class="media" ';
        $tag .= 'alt="PubChem image '.$cid.'" ';
        $tag .= 'title="CID:'.$cid.'  Click to PubChem page"/></a></div>';
        return $tag;
  }

 /**
  * Get properties from XML data
  * @param string $cid
  * @return boolean
  */
  function getProperties($cid){
    $xml = $this->getPubchemXml($cid);
    if ($xml===false){
      return false;
    }
    $x = new Xml;
    $XmlClass = $x->GetXmlObject($xml);
    $xmls = $XmlClass[0]->next[0]->next;
    $ext_props = array();
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
              $ext_props['formula'] = $this->getChemFormat($info_value);
              break;
            case "IUPAC Name":
              if ($info_name2=="Preferred"||$info_name2=="Allowed"){
                if(strlen($info_value)>50){ $info_value = str_replace("-","- ",$info_value);}
                $ext_props['iupac'] = $info_value;
              }
              break;
            case "Molecular Weight":
              $ext_props['mw'] = $info_value;
              break;
            case "Log P":
              $ext_props['xlogp'] = $info_value;
              break;
            case "SMILES":
              $ext_props['smiles'] = $info_value;
              break;
          }
        }
      }
    }
    // save extracted properties
    $this->propCache->PutMediaText($cid,json_encode($ext_props));
    return $ext_props;
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
  /**
   * table name row for a template
   * @param Doku_Renderer $renderer
   * @param string $cid
   * @param string $iupac
   * @param string $title
   */
  function _name_row(&$renderer,$cid,$iupac='',$title=''){
      $renderer->tablerow_open();
      $renderer->tableheader_open();
      $renderer->doc.=($title!='')?$this->getLang('mol_name'):'CID';
      $renderer->tableheader_close();
      $renderer->tablecell_open();
      $renderer->doc.=($title!='')?$title:$cid;
      if($iupac){
          $renderer->footnote_open();
          $renderer->doc.=$iupac;
          $renderer->footnote_close();
      }
      $renderer->tablecell_close();
      $renderer->tablerow_close();
  }

  /**
   * Render table row for a template
   * @param Doku_Renderer $renderer
   * @param string $head
   * @param string $cell
   */
  function _row(&$renderer,$head,$cell){
      if(empty($cell))return;
      $renderer->tablerow_open();
      $renderer->tableheader_open();
      $renderer->doc.=$head;
      $renderer->tableheader_close();
      $renderer->tablecell_open();
      $renderer->doc.=$cell;
      $renderer->tablecell_close();
      $renderer->tablerow_close();
  }
}
?>
