<?php

namespace pdima88\icms2ulogin\backend\forms;

use cmsForm;
use cmsCore;
use fieldString;
use fieldList;
use fieldCheckbox;

class form_options extends cmsForm {

    public function init() {

	    $model = cmsCore::getModel('ulogin');
	    $group_id = $model->getUloginGroupId();

        return array(

            array(
                'type' => 'fieldset',
                'childs' => array(
					new fieldCheckbox('login_form', [
						'title' => 'Встроить в форму входа',
					]),
                    new fieldString('uloginid', array(
                        'title' => 'Значение поля <b>uLogin ID</b>',
                        'default' => '',
                    )),

	                new fieldList('group_id', array(
			                'title' => 'Группа для новых пользователей',
			                'default' => $group_id,
			                'generator' => function () {
				                $users_model = cmsCore::getModel('users');
				                $groups_list = $users_model->getGroups();
				                $groups = array_collection_to_list($groups_list, 'id', 'title');
				                return $groups;
			                }
		                )
	                ),



                )
            ),

        );

    }

}
