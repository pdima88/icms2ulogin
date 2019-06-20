<?php

namespace pdima88\icms2ulogin;

class manifest {

	public function hooks() {
		return array(

			'hooks' => array(
//		'user_auth_error',
				'users_profile_view',
			)

		);
	}


	public function getRootPath() {
		return realpath(dirname(__FILE__).'/..');
	}

}
