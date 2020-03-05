<?php

namespace pdima88\icms2ulogin\widgets\panel;

use cmsWidget;
use cmsCore;
use cmsUser;
use pdima88\icms2ulogin\frontend as ulogin;

class widget extends cmsWidget {

	public $is_cacheable = false;

    public function run(){

	    cmsUser::getInstance();

	    if (cmsUser::isLogged()){ return false; }

	    $uloginid = $this->getOption('uloginid');

	    if (empty($uloginid)) {
		    $ulogin = cmsCore::getController('ulogin');
		    $uloginid = $ulogin->getOptions();
            $uloginid = !empty($uloginid['uloginid']) ? $uloginid['uloginid'] : '';
	    }

	    if (empty($uloginid)) {
		    $uloginid = '';
	    }

	    $u_id = 'ulogin_' . $uloginid . '_' . ulogin::$u_inc++;

	    $callback = 'uloginCallback';
	    $redirect = urlencode(href_to_abs('ulogin','login'));

		if ($this->getOption('hidden')) {
			$this->setTemplate('panel_hidden');
		} else {
			if (empty($uloginid)) {				
				$this->setTemplate('panel_default');
			} else {
				$this->setTemplate('panel');
			}
		}

        return array(
	        'id' => $u_id,
	        'uloginid' => $uloginid,
	        'callback' => $callback,
	        'redirect' => $redirect,
        );

    }

}
