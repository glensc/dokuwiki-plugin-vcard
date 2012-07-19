<?php
/**
 * vCard Plugin: creates a link to download a vCard file
 * uses the vCard class by Kai Blankenhorn <kaib@bitfolge.de>
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <esther@kaffeehaus.ch>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

// maintain a global count of the number of expandable vcards in the page, 
// this allows each to be uniquely identified
global $plugin_folded_count;
if (!isset($plugin_folded_count)) $plugin_folded_count = 0;
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_vcard extends DokuWiki_Syntax_Plugin {
 
    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Esther Brunner',
            'email'  => 'esther@kaffeehaus.ch',
            'date'   => '2007-05-16',
            'name'   => 'vCard Plugin',
            'desc'   => 'creates a link to download a vCard file',
            'url'    => 'http://wiki.splitbrain.org/plugin:vcard',
        );
    }
 
    function getType(){ return 'substition'; }
    function getSort(){ return 314; }
    function connectTo($mode) { $this->Lexer->addSpecialPattern("\{\{vcard>.*?\}\}",$mode,'plugin_vcard'); }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        
        // strip markup
        $match = substr($match,8,-2);

        // split address from rest and divide it into parts
        list($match,$address) = explode('|',$match,2);
        if ( $address ){
            list($street,$place,$country) = explode(',',$address,3);
            list($zip,$city) = explode(' ',trim($place),2);
# print('Ulice: ' .$street.', PSC: '.$zip.', Mesto: '.$city.', Zeme: '.$country.'<br/>');
        }
        
        // split phone(s) from rest and create an array
        list($match,$phones) = explode('#',$match,2);
        $phones = explode('&',$phones);
        foreach ( $phones as $phone) $phone = trim($phone);
# print('Work '.$phones[0].' Mobile '.$phones[1].'Home '.$phones[2].'Fax '.$phones[3].'<br/>');

        // get birthday
        if (preg_match('/\d{4}\-\d{2}\-\d{2}/',$match,$birthday)){
            $birthday = $birthday[0];
        }
        
        // get website
        $punc = '.:?\-;,';
        $any  = '\w/~:.?+=&%@!\-';
        if (preg_match('#http://['.$any.']+?(?=['.$punc.']*[^'.$any.'])#i',$match,$website)){
             $website = $website[0];
        }
        
        // get e-mail address
        if (preg_match('/<[\w0-9\-_.]+?@[\w\-]+\.[\w\-\.]+\.*[\w]+>/i',$match,$email,PREG_OFFSET_CAPTURE)){
             $match = substr($match,0,$email[0][1]);
             $email = substr($email[0][0],1,-1);
        }
# print('Email: '.$email.'<br>');

        // get company name
        if (preg_match('/\[(.*)\]/',$match,$company)){
	    $match = str_replace($company[0], '', $match);
	    $company=$company[1];
# print('Company: '.$company.'<br>');
        #     $match = substr($match,0,$email[0][1]);
        #     $email = substr($email[0][0],1,-1);
        }

# print('Jmeno: '.$match.'<br>');        
        // the rest is the name
        $match = trim($match);
        $pos = strrpos($match,' ');
        if($pos !== false){
            list($first,$middle) = explode(' ',substr($match,0,$pos),2);
            $last   = substr($match,$pos+1);
        } else {
            $first  = $match;
            $middle = NULL;
            $last   = NULL;
        }
        
        return array($first,$middle,$last,$email,$website,$birthday,$phones,trim($street),$zip,$city,trim($country),$company);
    } 

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $plugin_folded_count;
        global $conf;

        if($mode == 'xhtml'){
            $plugin_folded_count++;

            $link = array();
            $link['class']  = 'urlextern';
            $link['style']  = 'background-image: url('.DOKU_BASE.'lib/plugins/vcard/vcf.gif)';
            $link['pre']    = '';
            $link['suf']    = '';
            $link['more']   = 'rel="nofollow"';
            $link['target'] = '';
        
            $script = DOKU_URL.'lib/plugins/vcard/vcard.php';
            $folded = '';
            $script .= '?first='.urlencode($data[0]);
            if ( $data[1] ) $script .= '&middle='.urlencode($data[1]);
            if ( $data[2] ) $script .= '&last='.urlencode($data[2]);
	    if ( $data[11] ) {
		$script .= '&org='.urlencode($data[11]);
                $folded .= '<b>'.$data[11].'</b>';
	    }
            if ( $data[3] ){
                $email = $data[3];
                $script .= '&email='.urlencode($data[3]);
                $folded .= ' <a href="mailto:'.$data[3].'" class="mail">'.$email.'</a>';
            }
            if ( $data[6][0] ){
                $script .= '&work='.urlencode(trim($data[6][0]));
                $folded .= ' <b>Work:</b> '.$renderer->_xmlEntities($data[6][0]);
            }
            if ( $data[6][1] ){
                $script .= '&cell='.urlencode(trim($data[6][1]));
                $folded .= ' <b>Mobile:</b> '.$renderer->_xmlEntities($data[6][1]);
            }
            if ( $data[6][2] ) {
		$script .= '&home='.urlencode(trim($data[6][2]));
		$folded .= ' <b>Home:</b> '.$renderer->_xmlEntities($data[6][1]);
	    }
            if ( $data[4] ){
                $script .= '&website='.urlencode($data[4]);
                $folded .= ' <a href="'.$data[4].'" class="urlextern" target="'.$conf['target']['extern'].'" onclick="return svchk()" onkeypress="return svchk()" rel="nofollow">'.$renderer->_xmlEntities($data[4]).'</a>';
            }
            if ( $data[5] ) $script .= '&birthday='.$data[5];
            if ( $data[6][3] ) $script .= '&fax='.urlencode(trim($data[6][3]));
            if ( $data[7] ){
                $script .= '&street='.urlencode($data[7]);
                $folded .= ' '.$renderer->_xmlEntities($data[7]).',';
            }
            if ( $data[8] ){
                $script .= '&zip='.urlencode($data[8]);
                $folded .= ' '.$renderer->_xmlEntities($data[8]);
            }
            if ( $data[9] ){
                $script .= '&city='.urlencode($data[9]);
                $folded .= ' '.$renderer->_xmlEntities($data[9]);
            }
            if ( $data[10] ) $script .= '&country='.urlencode($data[10]);
            
            $link['title']  = $email;
            $link['url']    = $script;
            $link['name']   = $renderer->_xmlEntities($data[0].( $data[2] ? ' '.$data[2] : '' ));

            $renderer->doc .= $renderer->_formatLink($link);
            if ( @file_exists(DOKU_INC.'lib/plugins/folded/closed.gif') && ($folded) ){
                // folded plugin is installed: enables additional feature
		$renderer->doc .= '<a href="#" class="folder" onclick="fold(this, '.$plugin_folded_count.');">';
		$renderer->doc .= '<img src="'.DOKU_BASE.'lib/plugins/folded/closed.gif" alt="Skryty obsah" title="Zobraz" /></a>';
		$renderer->doc .= '<span class="folded" id="folded_'.$plugin_folded_count.'" style="display:none;">';
                $renderer->doc .= $folded;
                $renderer->doc .= '</span>';
            }
            
            return true;
        }
        return false;
    }
    
    function _mailShield($address){
        global $conf;
        
        //shields up
        // copied from emaillink() in xhtml.php
        if($conf['mailguard']=='visible'){
            //the mail name gets some visible encoding
            $address = str_replace('@',' [at] ',$address);
            $address = str_replace('.',' [dot] ',$address);
            $address = str_replace('-',' [dash] ',$address);
            return $renderer->_xmlEntities($address);
            
        }elseif($conf['mailguard']=='hex'){
            //encode every char to a hex entity
            for ($x=0; $x < strlen($address); $x++) {
                $encode .= '&#x' . bin2hex($address[$x]).';';
            }
            return $encode;

        }else{
            //keep address as is
#            return $renderer->_xmlEntities($address);

        }
    
    }
     
}
 
//Setup VIM: ex: et ts=4 enc=utf-8 :
?>