<?php
namespace pdima88\icms2ulogin\hooks;

use cmsAction;
use cmsTemplate;
use cmsUser;
use pdima88\icms2ulogin\frontend as ulogin;

class login_form_html extends cmsAction
{
    public function run()
    {
        $options = $this->getOptions();

        if (!$options['login_form']) return '';
        if (cmsUser::isLogged()) return '';
        
        $uloginid = $options['uloginid'] ?? '';
        
        if (empty($uloginid)) {
            $uloginid = '';
        }

        $u_id = 'ulogin_' . $uloginid . '_' . ulogin::$u_inc++;

        $callback = 'uloginCallback';
        $redirect = urlencode(href_to_abs('ulogin','login'));

        /*if (empty($uloginid)) {
            $this->setTemplate('panel_default');
        } else {
            $this->setTemplate('panel');
        }*/

        ob_start();
        cmsTemplate::getInstance()->renderControllerChild($this->name, 'login',
            [
                'id' => $u_id,
                'uloginid' => $uloginid,
                'callback' => $callback,
                'redirect' => $redirect,
            ]
        );
        $html = ob_get_clean();
        return $html;
    }
}
