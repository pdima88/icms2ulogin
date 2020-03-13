<?php

namespace pdima88\icms2ulogin\widgets\networks;

use cmsWidget;
use cmsUser;
use cmsCore;
use pdima88\icms2ulogin\frontend as ulogin;
use cmsController;

class widget extends cmsWidget {

	public $is_cacheable = false;

    public function run(){

	    if (!cmsUser::isLogged()){ return false; }

	    cmsCore::getController('ulogin');

	    $is_profile = ulogin::$is_profile;
	    ulogin::$is_profile = false;

	    if (!isset($is_profile) || $is_profile != true) {
		    if ($this->getOption('in_profile_only')) {
			    return false;
		    }
	    }


	    $ulogin_model = cmsCore::getModel('ulogin');
	    $networks = $ulogin_model->getUloginUserNetworks( cmsUser::getInstance()->id );

	    $editable = $this->getOption('editable');

	    if ($editable) {

		    $uloginid = $this->getOption('uloginid');

		    if (empty($uloginid)) {
			    $uloginid = cmsController::loadOptions('ulogin');
				$uloginid = !empty($uloginid['uloginid']) ? $uloginid['uloginid'] : '';
		    }

		    if (empty($uloginid)) {
			    $uloginid = '';
		    }

		    $u_id =  uniqid('ulogin_',true);

		    $callback = 'uloginCallback';
		    $redirect = urlencode(href_to_abs('ulogin','login'));

		    $add_str = $this->getOption('add_str');
		    $delete_str = $this->getOption('delete_str');


		    if (empty($uloginid)) {
			    $this->setTemplate('networks_default');
		    } else {
			    $this->setTemplate('networks_editable');
		    }

		    return array(
			    'id' => $u_id,
			    'uloginid' => $uloginid,
			    'callback' => $callback,
			    'redirect' => $redirect,
			    'networks' => $networks,
			    'add_str' => $add_str,
			    'delete_str'=> $delete_str,
		    );
	    }

        return array(
	        'networks' => $networks,
        );

    }

}
