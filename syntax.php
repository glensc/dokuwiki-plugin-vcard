<?php
/**
 * vCard Plugin: creates a link to download a vCard file
 * uses the vCard class by Kai Blankenhorn <kaib@bitfolge.de>
 *
 * Can also output hCard microformat:
 * @link http://microformats.org/wiki/hcard
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <esther@kaffeehaus.ch>
 * @author     Jürgen A.Lamers <jaloma.ac@googlemail.de>
 * @author     Elan Ruusamäe <glen@delfi.ee>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_vcard extends DokuWiki_Syntax_Plugin {
	/**
	 * plugin folded is present and enabled
	 * @var bool $have_folded
	 */
	private $have_folded = false;

	public function __construct() {
		$this->have_folded = !plugin_isdisabled('folded');
	}

	function getType(){ return 'substition'; }
	function getSort(){ return 314; }
	function connectTo($mode) { $this->Lexer->addSpecialPattern("{{vcard>.*?}}", $mode, 'plugin_vcard'); }

	/**
	 * Handle the match
	 */
	function handle($match, $state, $pos, &$handler) {
		// strip markup
		$match = substr($match, 8, -2);

		// split address from rest and divide it into parts
		list($match, $address) = explode('|', $match, 2);
		if ($address) {
			list($street, $place, $country) = explode(',', $address, 3);
			list($zip, $city) = explode(' ', trim($place), 2);
		}

		// split phone(s) from rest and create an array
		list($match, $phones) = explode('#', $match, 2);
		$phones = explode('&', $phones);
		foreach ($phones as $phone) {
			$phone = trim($phone);
		}

		// get birthday
		if (preg_match('/\d{4}\-\d{2}\-\d{2}/', $match, $m)) {
			$birthday = $m[0];
		}

		// get website
		$punc = '.:?\-;,';
		$any = '\w/~:.?+=&%@!\-';
		if (preg_match('#http://['.$any.']+?(?=['.$punc.']*[^'.$any.'])#i',$match, $m)) {
			$website = $m[0];
		}

		// get e-mail address
		if (preg_match('/<[\w0-9\-_.]+?@[\w\-]+\.[\w\-\.]+\.*[\w]+>/i', $match, $email, PREG_OFFSET_CAPTURE)) {
			$match = substr($match,0,$email[0][1]);
			$email = substr($email[0][0],1,-1);
		}

		// get company name
		if (preg_match('/\[(.*)\]/', $match, $m)) {
			$match = str_replace($m[0], '', $match);
			$company = $m[1];
		}

		// the rest is the name
		$match = trim($match);
		$pos = strrpos($match, ' ');
		if ($pos !== false) {
			list($first,$middle) = explode(' ', substr($match, 0, $pos), 2);
			$last  = substr($match, $pos + 1);
		} else {
			$first = $match;
			$middle = null;
			$last  = null;
		}

		return array(
			'first' => $first,
			'middle' => $middle,
			'last' => $last,
			'email' => $email,
			'website' => $website,
			'birthday' => $birthday,
			'work' => trim($phones[0]),
			'cell' => trim($phones[1]),
			'home' => trim($phones[2]),
			'fax' => trim($phones[3]),
			'street' => trim($street),
			'zip' => $zip,
			'city' => $city,
			'country' => trim($country),
			'org' => $company,
		);
	}

	/**
	 * Create output
	 */
	function render($mode, &$renderer, $data) {
		if ($mode != 'xhtml') {
			return false;
		}

		global $conf;

		$hcard = $this->getConf('do_hcard');

		$folded = '';
		$fullname = '';

		if ($hcard) {
			$fullname .= $this->_tag('given-name', $data['first'], 'class="given-name"');
		}

		if ($data['middle']) {
			if ($hcard) {
				$fullname .= ' '.$this->_tag('additional-name', $data['middle'], 'class="additional-name"');
			}
		}

		if ($data['last']) {
			if ($hcard) {
				$fullname .= ' '.$this->_tag('family-name', $data['last'], 'class="family-name"');
			}
		}

		if ($hcard) {
			$folded .= '<'.$this->getConf('tag_folded').'>';
		}

		if ($data['org']) {
			if ($hcard) {
				$folded .= $this->_tag('org', '<b class="org">'.$data['org'].'</b>');
			} else {
				$folded .= '<b>'.$data['org'].'</b>';
			}
		}

		if ($data['email']) {
			if ($hcard) {
				$folded .= ' <b>'.$this->getLang('email').'</b> ';
				$folded .= ' <a href="mailto:'.$data['email'].'" class="mail">'.$data['email'].'</a>';
				$mailto .= ' <a href="mailto:'.$data['email'].'" class="mail"></a>';
			} else {
				$folded .= ' <a href="mailto:'.$data['email'].'" class="mail">'.$data['email'].'</a>';
				$mailto .= ' <a href="mailto:'.$data['email'].'" class="mail"></a>';
			}
		}

		if ($data['work']) {
			if ($hcard) {
				$html = '<b>work</b> '. $renderer->_xmlEntities($data['work']);
				$tel = $this->_tag('tel_type_work', $html, 'class="type"');
				$folded .= ' '.$this->_tag('tel', $tel, 'class="tel"');
			} else {
				$folded .= ' <b>'.$this->getLang('work').':</b> '.
				$renderer->_xmlEntities($data['work']);
			}
		}
		// 1: Mobile
		if ($data['cell']) {
			if ($hcard) {
				$html = '<b>cell</b> '. $renderer->_xmlEntities($data['cell']);
				$tel = $this->_tag('tel_type_cell', $html, 'class="type"');
				$folded .= ' '.$this->_tag('tel', $tel, 'class="tel"');
			} else {
				$folded .= ' <b>'.$this->getLang('cell').':</b> '.$renderer->_xmlEntities($data['cell']);
			}
		}
		// 2: Home
		if ($data['home']) {
			if ($hcard) {
				$html = '<b>home</b> '. $renderer->_xmlEntities($data['home']);
				$tel = $this->_tag('tel_type_home', $html, 'class="type"');
				$folded .= ' '.$this->_tag('tel', $tel, 'class="tel"');
			} else {
				$folded .= ' <b>'.$this->getLang('home').'</b> '.$renderer->_xmlEntities($data['home']);
			}
		}

		if ($data['website']) {
			if ($hcard) {
				$folded .= ' <b>'.$this->getLang('website').'</b> ';
			}
			$folded .= ' <a href="'.$data['website'].'" class="urlextern';

			if ($hcard) {
				$folded .= ' url';
			}
			$folded .= '" target="'.$conf['target']['extern'].'" onclick="return svchk()" onkeypress="return svchk()" rel="nofollow">'.$renderer->_xmlEntities($website).'</a>';
		}

		if ($data['birthday']) {
			if ($hcard) {
				$html = '<b>birthday</b> '. $renderer->_xmlEntities($data['birthday']);
				$folded .= ' '.$this->_tag('bday', $html, 'class="bday"');
			}
		}

		// 6: $phones
		// 3: Fax
		if ($data['fax']) {
			if ($hcard) {
				$html = '<b>fax</b> '. $renderer->_xmlEntities($data['fax']);
				$tel = $this->_tag('tel_type_fax', $html, 'class="type"');
				$folded .= ' '.$this->_tag('tel', $tel, 'class="tel"');
			}
		}

		if ($data['street']) {
			if ($hcard) {
				$html = ' <b>'.$this->getLang('address').'</b> '. $renderer->_xmlEntities($data['street']);
				$folded .= ' '.$this->_tag('street-address', $html, 'class="street-address"');
			} else {
				$folded .= ' '.$renderer->_xmlEntities($data['street']).',';
			}
		}
		// 8: $zip
		if ($data['zip']) {
			$html = $renderer->_xmlEntities($data['zip']);
			if ($hcard) {
				$folded .= ' '.$this->_tag('postal-code', $html, 'class="postal-code"');
			} else {
				$folded .= ' '.$html;
			}
		}

		if ($data['city']) {
			$html = $renderer->_xmlEntities($data['city']);
			if ($hcard) {
				$folded .= ' '.$this->_tag('locality', $html, 'class="locality"');
			} else {
				$folded .= ' '.$html;
			}
		}

		if ($data['country']) {
			if ($hcard) {
				$html = $renderer->_xmlEntities($data['country']);
				$folded .= ' '.$this->_tag('country', $html, 'class="country-name"');
			}
		}

		if ($hcard) {
			$folded .= '</'.$this->getConf('tag_org').'>';
		}
		if ($hcard) {
			$renderer->doc .= '<'.$this->getConf('tag_vcard').' class="vcard">';
		}

		if ($this->getConf('email_shortcut')) {
			$renderer->doc .= $mailto. ' ';
		}

		$link = array();
		$link['class'] = 'urlextern iw_vcard';
		if ($hcard) {
			$link['class'] .= ' url fn n';
		}
		$link['pre']    = '';
		$link['suf']    = '';
		$link['more']   = 'rel="nofollow"';
		$link['target'] = '';
		$link['title']  = $email;
		$link['url'] = DOKU_URL.'lib/plugins/vcard/vcard.php?'.buildURLparams($data);

		if ($hcard) {
			$link['name'] = $fullname;
		} else {
			$link['name'] = $renderer->_xmlEntities($data['first']. ($data['last'] ? ' '.$data['last'] : ''));
		}
		$renderer->doc .= $renderer->_formatLink($link);

		if ($this->have_folded && $folded) {
			global $plugin_folded_count;

			$plugin_folded_count++;

			// folded plugin is installed: enables additional feature
			$renderer->doc .= '<a href="#folded_'.$plugin_folded_count.'" class="folder"></a>';
			$renderer->doc .= '<span class="folded hidden" id="folded_'.$plugin_folded_count.'">';
			$renderer->doc .= $folded;
			$renderer->doc .= '</span>';
		}

		if ($hcard) {
			$renderer->doc .= '</'.$this->getConf('tag_vcard').'>';
		}

		return true;
	}

	/**
	 * create $tag with $params
	 */
	private function _tag($tag_, $text, $params = '') {
		$tag = $this->getConf("tag_$tag_");
		if (!$tag) {
			$tag = 'span';
		}
		$html = '';
		$html .= '<'.$tag;
		if ($params) {
			$html .= ' '. $params;
		}
		$html .= '>'.$text;
		$html .= '</'.$tag.'>';
		return $html;
	}
}

//Setup VIM: ex: noet ts=4 sw=4 enc=utf-8 :
