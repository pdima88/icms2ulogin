<?php

namespace pdima88\icms2ulogin\hooks;

use cmsAction;
use cmsUser;
use pdima88\icms2ulogin\frontend as ulogin;

class users_profile_view extends cmsAction
{

	public function run($profile)
	{

		$user = cmsUser::getInstance();

		if (!$user->is_logged) {
			ulogin::$is_profile = false;
			return $profile;
		}

		if ($user->id == $profile['id']) {
			ulogin::$is_profile = true;
		} else {
			ulogin::$is_profile = false;
		}

		return $profile;

	}

}
