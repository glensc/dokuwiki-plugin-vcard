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
			'given-name' => $first,
			'additional-name' => $middle,
			'family-name' => $last,
			'email' => $email,
			'website' => $website,
			'bday' => $birthday,
			'work' => trim($phones[0]),
			'cell' => trim($phones[1]),
			'home' => trim($phones[2]),
			'fax' => trim($phones[3]),
			'street-address' => trim($street),
			'postal-code' => $zip,
			'locality' => $city,
			'country-name' => trim($country),
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

		$html = '';
		$hcard = $this->getConf('do_hcard');

		if ($this->getConf('email_shortcut')) {
			$html .= ' '.$this->_emaillink($renderer, $data['email']).' ';
		}

		if ($hcard) {
			$name = $this->_tagclass('given-name', $data['given-name']);

			if ($data['additional-name']) {
				$name .= ' '.$this->_tagclass('additional-name', $data['additional-name']);
			}

			if ($data['family-name']) {
				$name .= ' '.$this->_tagclass('family-name', $data['family-name']);
			}
		} else {
			$name = $renderer->_xmlEntities($data['given-name']. ($data['family-name'] ? ' '.$data['family-name'] : ''));
		}

		$url = DOKU_URL.'lib/plugins/vcard/vcard.php?'.buildURLparams($data);
		$html .= $this->_weblink($renderer, $url, $name, 'iw_vcard url fn n', $data['email']);

		if ($this->have_folded) {
			global $plugin_folded_count;
			$plugin_folded_count++;

			// folded plugin is installed: enables additional feature
			$html .= '<a href="#folded_'.$plugin_folded_count.'" class="folder"></a>';
			$html .= '<span class="folded hidden" id="folded_'.$plugin_folded_count.'">';

			if ($hcard) {
				$html .= $this->_tag('folded', $this->_folded_hcard($renderer, $data));
			} else {
				$html .= $this->_folded_vcard($renderer, $data);
			}

			$html .= '</span>';
		}

		if ($hcard) {
			$renderer->doc .= $this->_tagclass('vcard', $html);
		} else {
			$renderer->doc .= $html;
		}

		return true;
	}

	/**
	 * Build hCard formatted folded body
	 */
	private function _folded_hcard(&$renderer, $data) {
		$folded = '';

		if ($data['org']) {
			$folded .= $this->_tag('org', '<b class="org">'.$data['org'].'</b>');
		}

		if ($data['email']) {
			$folded .= ' <b>'.$this->getLang('email').'</b> ';
			$folded .= $this->_emaillink($renderer, $data['email'], $data['email']);
		}

		if ($data['work']) {
			$folded .= ' '.$this->_tel($renderer, 'work', $data['work']);
		}

		if ($data['cell']) {
			$folded .= ' '.$this->_tel($renderer, 'cell', $data['cell']);
		}

		if ($data['home']) {
			$folded .= ' '.$this->_tel($renderer, 'home', $data['home']);
		}

		if ($data['website']) {
			$folded .= ' <b>'.$this->getLang('website').'</b> ';
			$folded .= $this->_weblink($renderer, $data['website'], $renderer->_xmlEntities($data['website']), 'url');
		}

		if ($data['bday']) {
			$html = '<b>birthday</b> '. $renderer->_xmlEntities($data['bday']);
			$folded .= ' '.$this->_tagclass('bday', $html);
		}

		if ($data['fax']) {
			$folded .= ' '.$this->_tel($renderer, 'fax', $data['fax']);
		}

		if ($data['street-address']) {
			$html = ' <b>'.$this->getLang('address').'</b> '. $renderer->_xmlEntities($data['street-address']);
			$folded .= ' '.$this->_tagclass('street-address', $html);
		}

		if ($data['postal-code']) {
			$html = $renderer->_xmlEntities($data['postal-code']);
			$folded .= ' '.$this->_tagclass('postal-code', $html);
		}

		if ($data['locality']) {
			$html = $renderer->_xmlEntities($data['locality']);
			$folded .= ' '.$this->_tagclass('locality', $html);
		}

		if ($data['country-name']) {
			$html = $renderer->_xmlEntities($data['country-name']);
			$folded .= ' '.$this->_tagclass('country-name', $html);
		}

		return $folded;
	}

	/**
	 * Build plain formatting for vCard
	 */
	private function _folded_vcard(&$renderer, $data) {
		$folded = '';

		if ($data['org']) {
			$folded .= '<b>'.$data['org'].'</b>';
		}

		if ($data['email']) {
			$folded .= ' '.$this->_emaillink($renderer, $data['email'], $data['email']);
		}

		if ($data['work']) {
			$html = $renderer->_xmlEntities($data['work']);
			$folded .= ' <b>'.$this->getLang('work').':</b> '. $html;
		}

		if ($data['cell']) {
			$html = $renderer->_xmlEntities($data['cell']);
			$folded .= ' <b>'.$this->getLang('cell').':</b> '. $html;
		}

		if ($data['home']) {
			$html = $renderer->_xmlEntities($data['home']);
			$folded .= ' <b>'.$this->getLang('home').'</b> '. $html;
		}

		if ($data['website']) {
			$folded .= $this->_weblink($renderer, $data['website'], $renderer->_xmlEntities($data['website']), 'url');
		}

		if ($data['street-address']) {
			$folded .= ' '.$renderer->_xmlEntities($data['street-address']).',';
		}

		if ($data['postal-code']) {
			$folded .= ' '.$renderer->_xmlEntities($data['postal-code']);
		}

		if ($data['locality']) {
			$folded .= ' '.$renderer->_xmlEntities($data['locality']);
		}

		return $folded;
	}

	/**
	 * create $tag with $class
	 */
	private function _tag($tag_, $text, $class = '') {
		$tag = $this->getConf("tag_$tag_");
		if (!$tag) {
			$tag = 'span';
		}
		$html = '';
		$html .= '<'.$tag;
		if ($class) {
			$html .= ' class="'. $class. '"';
		}

		// if tag is 'abbr', use translation and value in title
		if ($tag == 'abbr') {
			$html .= ' title="'. $text. '"';
			$html .= '>'.$this->getLang($text);
		} else {
			$html .= '>'.$text;
		}
		$html .= '</'.$tag.'>';
		return $html;
	}

	/**
	 * create tag with class as $tag
	 */
	private function _tagclass($tag, $text) {
		return $this->_tag($tag, $text, $tag);
	}

	/**
	 * normalize telephone number to include all non-numeric outside class=value
	 * see http://microformats.org/wiki/value-class-pattern
	 */
	private function _tel_normalize($value) {
		$res = array();

		// match all but +, digits and space
		if (!preg_match_all('/[^+\d\s]+/', $value, $matches, PREG_OFFSET_CAPTURE)) {
			$res[] = array('+', $value);
			return $res;
		}

		$offset  = 0;
		foreach ($matches[0] as $match) {
			$v = substr($value, $offset, $match[1] - $offset);
			if ($v) {
				$res[] = array('+', $v);
			}
			$res[] = array('-', $match[0]);
			$offset = $match[1] + strlen($match[0]);
		}
		$v = substr($value, $offset);
		if ($v) {
			$res[] = array('+', $v);
		}
		return $res;
	}

	/**
	 * format hcard telephone numbers
	 */
	private function _tel(&$renderer, $type, $value) {

		$type = '<b>'.$this->_tag('tel_type_'.$type, $type, 'type').'</b> ';

		$values = '';
		foreach ($this->_tel_normalize($value) as $res) {
			list($t, $value) = $res;
			if ($t === '+') {
				$values .= $this->_tag('tel_value', $renderer->_xmlEntities($value), 'value');
			} else {
				$values .= $value;
			}
		}

		return $this->_tagclass('tel', $type.$values);
	}

	private function _emaillink(&$renderer, $mail, $name = '') {
		return $renderer->_formatLink(array(
			'url' => 'mailto:'.$mail,
			'name' => $name,
			'class'=> 'email',
		));
	}

	private function _weblink(&$renderer, $url, $name = '', $class = '', $title ='') {
		global $conf;
		return $renderer->_formatLink(array(
			'url' => $url,
			'name' => $name,
			'title' => $title,
			'rel' => 'nofollow',
			'target' => $conf['target']['extern'],
			'class'=> trim('urlextern '. $class),
		));
	}
}

//Setup VIM: ex: noet ts=4 sw=4 enc=utf-8 :
