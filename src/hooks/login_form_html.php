<?php
namespace pdima88\icms2ulogin\hooks;

use cmsAction;

class login_form_html extends cmsAction
{

    public function run()
    {

        return \cmsTemplate::getInstance()->renderControllerChild($this->name, 'login',
            [
                'id' => 1,
                'uloginid' => 2,
                'redirect' => 3,
                'callback' => 4
            ]
        );
    }
}
