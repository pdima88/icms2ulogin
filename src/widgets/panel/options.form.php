<?php

namespace pdima88\icms2ulogin\widgets\panel;

use cmsForm;
use fieldString;

class form_options extends cmsForm {

	public function init() {

		//cmsCore::loadControllerLanguage('ulogin');

		return array(

			array(
				'type' => 'fieldset',
				'title' => LANG_OPTIONS,
				'childs' => array(

					new fieldString('options:uloginid', array(
							'title' => 'Значение поля <b>uLogin ID</b>',
							'hint' => 'Заполните это поле, если хотите, чтобы значение было отличным от заданного в опциях компонента uLogin',
							'default' => '',
						)
					),

				)
			),

		);

	}

}
