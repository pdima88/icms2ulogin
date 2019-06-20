<?php

namespace pdima88\icms2ulogin;

class manifest {

	public function hooks() {
		return [
//		'user_auth_error',
			'users_profile_view',
			'login_form_html',
		];
	}


	public function getRootPath() {
		return realpath(dirname(__FILE__).'/..');
	}

}
