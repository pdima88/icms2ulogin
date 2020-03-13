<?php

namespace pdima88\icms2ulogin\widgets\panel;

use cmsWidget;
use cmsCore;
use cmsUser;

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

	    $u_id = uniqid('ulogin_',true);

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
