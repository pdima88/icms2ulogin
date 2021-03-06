<?php

namespace pdima88\icms2ulogin\actions;

use cmsAction;
use cmsUser;
use auth;
use cmsCore;
use cmsConfig;
use cmsEventsManager;
use cmsUploader;
use cmsModel;
use pdima88\icms2ulogin\model;
use tableUsers;

/**
 *
 * @property-read model $model
 */
class login extends cmsAction
{
    /** Group of users with confirmed emails*/
    const GROUP_CONFIRMED = 4;

    protected $u_data;
    protected $currentUserId;
    protected $_messParam = array();
    protected $_doRedirect = true;

    public function run()
    {
        $title = '';

        $this->currentUserId = cmsUser::getInstance()->id;

        if ($this->request->isAjax()) {
            $this->_doRedirect = false;
        }

        $msg = t('ulogin:success', 'Вход успешно выполнен');
        if (cmsUser::isLogged()) {
            $msg = t('ulogin:addsuccess', 'Аккаунт успешно добавлен');
        }

        $this->uloginLogin($title, $msg);

        if ($this->request->isAjax()) {
            exit;
        }
    }

    /**
     * Отправляет данные как ответ на ajax запрос, если код выполняется в результате вызова callback функции,
     * либо добавляет сообщение в сессию для вывода в режиме redirect
     * @param array $params
     */
    protected function sendMessage($params = array())
    {
        if ($this->_doRedirect) {

            $class = ($params['answerType'] == 'error' || $params['answerType'] == 'success')
                ? $params['answerType']
                : 'info';

            if (!empty($params['script'])) {
                $params['msg'] .= $params['script'];
            }

            cmsUser::addSessionMessage((!empty($params['title']) ? $params['title'] . ' <br>' : '') . $params['msg'], $class);

            $this->redirectBack();

        } else {
            echo json_encode(array(
                'title' => isset($params['title']) ? $params['title'] : '',
                'msg' => isset($params['msg']) ? $params['msg'] : '',
                'answerType' => isset($params['answerType']) ? $params['answerType'] : '',
                'userId' => isset($params['userId']) ? $params['userId'] : '0',
                'existIdentity' => isset($params['existIdentity']) ? $params['existIdentity'] : '0',
                'networks' => isset($params['networks']) ? $params['networks'] : '',
                'redirect' => isset($params['redirect']) ? $params['redirect'] : '',
            ));
            exit;
        }
    }


    protected function uloginLogin($title = '', $msg = '')
    {
        $this->u_data = $this->uloginParseRequest();
        if (!$this->u_data) {
            return;
        }

        try {
            $u_user_db = $this->model->getUloginUserItem(array('identity' => $this->u_data['identity']));
            $user_id = 0;

            if ($u_user_db) {

                if ($this->model->checkUloginUserId($u_user_db['user_id'])) {
                    $user_id = $u_user_db['user_id'];
                }

                if (isset($user_id) && (int)$user_id > 0) {
                    if (!$this->checkCurrentUserId($user_id)) {
                        // если $user_id != ID текущего пользователя
                        return;
                    }
                } else {
                    // данные о пользователе есть в ulogin_table, но отсутствуют в таблице пользователей. Необходимо переписать запись в ulogin_table и в базе сайта.
                    $user_id = $this->newUloginAccount($u_user_db);
                }

            } else {
                // пользователь НЕ обнаружен в ulogin_table. Необходимо добавить запись в ulogin_table и в базе modx.
                $user_id = $this->newUloginAccount();
            }

            // обновление данных и Вход
            if ($user_id > 0) {
                $this->loginUser($user_id);

                $networks = $this->model->getUloginUserNetworks($user_id);
                $this->sendMessage(array(
                    'title' => $title,
                    'msg' => $msg,
                    'networks' => $networks,
                    'answerType' => 'success',
                    //					'redirect' => $redirect_url,
                ));
            }
            return;
        } catch (Exception $e) {
            $this->sendMessage(array(
                'title' => 'Ошибка при работе с БД.',
                'msg' => 'Exception: ' . $e->getMessage(),
                'answerType' => 'error'
            ));
            return;
        }
    }


    /**
     * Добавление в таблицу uLogin
     * @param $u_user_db - при непустом значении необходимо переписать данные в таблице uLogin
     */
    protected function newUloginAccount($u_user_db = '')
    {

        $u_data = $this->u_data;

        if ($u_user_db) {
            // данные о пользователе есть в ulogin_user, но отсутствуют
            // в базе пользователей сайта => удалить их
            $this->model->deleteUloginUser($u_user_db['id']);
        }

        if (!(new auth($this->request))->isEmailAllowed($u_data['email'])) {
            $this->sendMessage(array(
                'title' => 'Ошибка при регистрации.',
                'msg' => sprintf(LANG_AUTH_RESTRICTED_EMAIL, $u_data['email']),
                'answerType' => 'error'
            ));
            return false;
        }
        if (!(new auth($this->request))->isIPAllowed(cmsUser::get('ip'))) {
            $this->sendMessage(array(
                'title' => 'Ошибка при регистрации.',
                'msg' => sprintf(LANG_AUTH_RESTRICTED_IP, cmsUser::get('ip')),
                'answerType' => 'error'
            ));
            return false;
        }

        $CMSuser = $this->model->getUser(array(
            'email' => $u_data['email'],
        ));

        $check_m_user = false; // $check_m_user - есть ли пользователь с таким email
        $user_id = 0; // id юзера с тем же email

        if ($CMSuser) {
            $user_id = $CMSuser['id'];
            $check_m_user = true;
        }


        $isLoggedIn = cmsUser::isLogged(); // $isLoggedIn == true -> пользователь онлайн
        $currentUserId = $this->currentUserId;

        if (!$check_m_user && !$isLoggedIn) {
            // отсутствует пользователь с таким email в базе -> регистрация в БД
            $user_id = $this->regUser();
            $this->addUloginAccount($user_id);
        } else {
            // существует пользователь с таким email или это текущий пользователь
            if ((int)$u_data['verified_email'] != 1) {
                // Верификация аккаунта

                $this->sendMessage(
                    array(
                        'title' => 'Подтверждение аккаунта.',
                        'msg' => 'Электронный адрес данного аккаунта совпадает с электронным адресом существующего пользователя. ' .
                            '<br>Требуется подтверждение на владение указанным email.',
                        'script' => '<script type="text/javascript">uLogin.mergeAccounts("' . $this->request->get('token') . '")</script>',
                        'answerType' => 'verify',
                    )
                );
                return false;
            }

            $user_id = $isLoggedIn ? $currentUserId : $user_id;

            $other_u = $this->model->getUloginUserItem(array(
                'user_id' => $user_id,
            ));

            // Синхронизация аккаунтов
            if ($other_u && !$isLoggedIn && !isset($u_data['merge_account'])) {
                $this->sendMessage(
                    array(
                        'title' => 'Синхронизация аккаунтов.',
                        'msg' => 'С данным аккаунтом уже связаны данные из другой социальной сети. ' .
                            '<br>Требуется привязка новой учётной записи социальной сети к этому аккаунту.',
                        'script' => '<script type="text/javascript">uLogin.mergeAccounts("' . $this->request->get('token') . '","' . $other_u['identity'] . '")</script>',
                        'answerType' => 'merge',
                        'existIdentity' => $other_u['identity']
                    )
                );
                return false;
            }

            $this->addUloginAccount($user_id);
        }

        return $user_id;
    }


    /**
     * Регистрация пользователя в БД
     * @return mixed
     */
    protected function regUser()
    {
        $u_data = $this->u_data;

        $password = md5($u_data['identity'] . time() . mt_rand());

        $first_name = !empty($u_data['first_name']) ? $u_data['first_name'] : '';
        $last_name = !empty($u_data['last_name']) ? $u_data['last_name'] : '';
        $bdate = !empty($u_data['bdate']) ? $u_data['bdate'] : '';

        $CMSuser = array(
            'password1' => $password,
            'password2' => $password,
            'nickname' => $last_name.' '.$first_name,
            'email' => $u_data['email'],
            'site' => isset($u_data['profile']) ? $u_data['profile'] : '',
            'phone' => isset($u_data['phone']) ? $u_data['phone'] : '',
            'fname' => $first_name,
            'lname' => $last_name,
        );

        $options = $this->getOptions();
        $ulogin_group_id = !empty($options['group_id']) ? $options['group_id'] : -1;

        $ulogin_group_id = (($ulogin_group_id > 0) ? $ulogin_group_id : $this->model->getUloginGroupId());

        if ($ulogin_group_id) {
            $CMSuser['groups'] = array($ulogin_group_id);
        }

        if ($u_data['verified_email'] ?? false) {
            $CMSuser['email_confirmed'] = now();
            if (!isset($CMSuser['groups'])) $CMSuser['groups'] = [];
            $CMSuser['groups'][] = self::GROUP_CONFIRMED;
        }

        if ($bdate) {
            $CMSuser['birth_date'] = date('Y-m-d H:i:s', strtotime($bdate));
        }

        $city_id = isset($u_data['city'])
            ? $this->model->getCityId($u_data['city'], $u_data['country'])
            : 0;

        if ($city_id > 0) {
            $CMSuser['city'] = $city_id;
            $CMSuser['city_cache'] = $u_data['city'];
        }

        $users_model = cmsCore::getModel('users');
        $result = $users_model->addUser($CMSuser);

        //  см. system/controllers/auth/actions/register.php:187
        if ($result['success']) {

            $CMSuser['id'] = $result['id'];

            cmsUser::addSessionMessage('Регистрация прошла успешно', 'success');

            cmsEventsManager::hook('user_registered', $CMSuser);

        } else {
            $this->sendMessage(array(
                'title' => 'Ошибка при регистрации.',
                'msg' => 'Произошла ошибка при регистрации пользователя.',
                'answerType' => 'error'
            ));
            return false;
        }

        return $CMSuser['id'];
    }


    /**
     * Добавление записи в таблицу ulogin_user
     * @param $user_id
     * @return bool
     */
    protected function addUloginAccount($user_id)
    {
        $name = ($this->u_data['first_name'] ?? '').' '.($this->u_data['last_name'] ?? '');
        if ($name == ' ') {
            $name = $this->u_data['name'];
        }
        $user = $this->model->addUloginAccount(array(
            'user_id' => $user_id,
            'identity' => (string)$this->u_data['identity'],
            'network' => $this->u_data['network'],
            'name' => $name,
            'email' => $this->u_data['email'] ?? '',
            'data' => cmsModel::arrayToYaml($this->u_data),
        ));

        if (!$user) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'Не удалось записать данные об аккаунте.',
                'answerType' => 'error'
            ));
            return false;
        } else {
            $CMSuser = tableUsers::getById($user_id);
            if (!$CMSuser->email_confirmed && ($this->u_data['verified_email'] ?? false) &&
                ($CMSuser->email == ($this->u_data['email'] ?? ''))
            ) {
                $groups = $CMSuser->groups;
                $groups[] = self::GROUP_CONFIRMED;
                $data = [
                    'email_confirmed' => now(),
                    'groups' => $groups
                ];
                if ($CMSuser['fname'] == '') {
                    $data['fname'] = $this->u_data['first_name'] ?? '';
                }
                if ($CMSuser['lname'] == '') {
                    $data['lname'] = $this->u_data['last_name'] ?? '';
                }
                if ($CMSuser['regstatus'] == tableUsers::REG_STATUS_NEWBIE &&
                    (!empty($data['fname']) || !empty($data['lname']))
                ) {
                    $data['nickname'] = ($this->u_data['first_name'] ?? '').' '.($this->u_data['last_name'] ?? '');
                }
                $this->model_users->updateUser($user_id, $data);
            }
        }

        return true;
    }


    /**
     * Выполнение входа пользователя в систему по $user_id
     * @param $u_user
     * @param int $user_id
     */
    protected function loginUser($user_id = 0)
    {

        $u_data = $this->u_data;

        $CMSuser = $this->model->getUser(array('id' => $user_id));

        if (empty($CMSuser['id'])) {
            return false;
        }

        // обновление данных
        if (
            empty($CMSuser['avatar'])
            || empty($CMSuser['birth_date'])
            || empty($CMSuser['site'])
            || empty($CMSuser['phone'])
            || empty($CMSuser['city'])
        ) {
            $users = cmsCore::getModel('users');
            $CMSuser['avatar'] = empty($CMSuser['avatar']) && isset($u_data['photo_big']) ? $this->getAvatar($CMSuser) : $CMSuser['avatar'];

            $CMSuser['site'] = empty($CMSuser['site']) && isset($u_data['profile']) ? $u_data['profile'] : $CMSuser['site'];
            $CMSuser['phone'] = empty($CMSuser['phone']) && isset($u_data['phone']) ? $u_data['phone'] : $CMSuser['phone'];

            if ((empty($CMSuser['birth_date']) || $CMSuser['birth_date'] == '0000-00-00 00:00:00') && isset($u_data['bdate'])) {
                $CMSuser['birth_date'] = date('Y-m-d H:i:s', strtotime($u_data['bdate']));
            }

            if (!empty($CMSuser['city_id'])) {
                $CMSuser['city'] = $CMSuser['city_id'];
            } elseif (isset($u_data['city'])) {
                $CMSuser['city'] = $this->model->getCityId($u_data['city'], $u_data['country']);
            }

            $result = $users->updateUser($CMSuser['id'], $CMSuser);

            if ($result['errors']) {
                $this->sendMessage(
                    array(
                        'title' => '',
                        'msg' => 'Ошибка при обновлении дынных пользователя',
                        'answerType' => 'error',
                    )
                );
                return false;
            }
        }

        // вход
        // см. system/core/user.php:174
        $CMSuser = cmsEventsManager::hook('user_login', $CMSuser);

        cmsUser::sessionSet('user', array(
            'id' => $CMSuser['id'],
            'groups' => $CMSuser['groups'],
            'time_zone' => $CMSuser['time_zone'],
            'perms' => cmsUser::getPermissions($CMSuser['groups'], $CMSuser['id']),
            'is_admin' => $CMSuser['is_admin'],
        ));

        $users_model = cmsCore::getModel('users');
        $users_model->update('{users}', $CMSuser['id'], array(
            'ip' => cmsUser::getIp()
        ));


        // см. system/controllers/auth/actions/login.php:30
        if (!cmsConfig::get('is_site_on')) {

            $userSession = cmsUser::sessionGet('user');

            if (!$userSession['is_admin']) {
                cmsUser::logout();
                $this->sendMessage(
                    array(
                        'title' => '',
                        'msg' => 'Войти на отключенный сайт может только администратор',//LANG_LOGIN_ADMIN_ONLY,
                        'answerType' => 'error',
                        'redirect' => $this->redirectBack(),
                    )
                );
                return false;
            }
        }

        cmsEventsManager::hook('auth_login', $CMSuser['id']);

        return true;
    }


    /**
     * Создание аватара
     */
    protected function getAvatar($user)
    {

        // см. system/core/uploader.php
        // см. system/controllers/images/frontend.php:38

        $u_data = $this->u_data;
        $uploader = new cmsUploader;
        $config = cmsConfig::getInstance();
        $path = '';
        $dest_file = '';
        $dest_dir = '';
        $dest_dir0 = '';
        $dest_ext = '';

        $file_url = (!empty($u_data['photo_big']))
            ? $u_data['photo_big']
            : (!empty($u_data['photo']) ? $u_data['photo'] : '');

        $q = !empty($file_url) ? true : false;

        if ($q) {

            $size = getimagesize($file_url);
            switch ($size[2]) {
                case IMAGETYPE_GIF:
                    $dest_ext = 'gif';
                    break;
                case IMAGETYPE_JPEG:
                    $dest_ext = 'jpg';
                    break;
                case IMAGETYPE_PNG:
                    $dest_ext = 'png';
                    break;
                default:
                    $dest_ext = 'jpg';
                    break;
            }

            $dir_num_user = sprintf('%03d', (int)($user['id'] / 100));
            $dir_num_file = sprintf('%03d', (int)($user['files_count'] / 100));
            $dest_dir0 = "{$dir_num_user}/u{$user['id']}/{$dir_num_file}";
            $dest_dir = $config->upload_path . $dest_dir0;

            @mkdir($dest_dir, 0777, true);

            $dest_file = substr(md5($user['id'] . $user['files_count'] . microtime(true)), 0, 8) . '.' . $dest_ext;
            $path = $dest_dir . '/' . $dest_file;

            $q = @copy($file_url, $path);
        }

        if ($q && !$uploader->isImage($path)) {
            $uploader->remove($path);
            $msg = 'Файл имеет неподходящий формат';
            $q = false;
        }

        if (!$q) {
            return array(
                'msg' => isset($msg) ? $msg : $msg = 'Произошла ошибка при формировании аватара',
                'answerType' => 'error',
            );
        }

        $defaultPresets = array(
            'normal' => array('width' => 256, 'height' => 256),
            'small' => array('width' => 64, 'height' => 64),
            'micro' => array('width' => 32, 'height' => 32),
        );
        $availablePresets = array_keys($defaultPresets);

        //достаем размеры изображений из настроек "загрузка изображений" (если он установлен) и индексируем их по именам
        if (cmsCore::isModelExists('images')) {
            $images_model = cmsCore::getModel('images');
            $presets_tmp = $images_model->getPresets();
            $presets = array();
            foreach ($presets_tmp as $tmp) {
                if (in_array($tmp['name'], $availablePresets)) {
                    $presets[$tmp['name']] = $tmp;
                }
            }
        } else {
            $presets = $defaultPresets;
        }

        uasort($presets, function ($a, $b) {
            return $a['height'] > $b['height'] ? -1 : ($a['height'] < $b['height'] ? 1 : 0);
        });

        $result['paths'] = array();
        foreach ($presets as $name => $data) {
            $dest_file = substr(md5($user['id'] . $user['files_count'] . microtime(true)), 0, 8) . '.' . $dest_ext;
            $path2 = $dest_dir . '/' . $dest_file;
	        if (img_resize($path, $path2, $data['width'], $data['height'], $data['is_square'])) {
                $result['paths'][$name] = $dest_dir0 . '/' . $dest_file;
            }
        }

        $uploader->remove($path);

        unset($path);
        return $result['paths'];
    }


    /**
     * Проверка текущего пользователя
     * @param $user_id
     * @return bool
     */
    protected function checkCurrentUserId($user_id)
    {
        $currentUserId = $this->currentUserId;
        if (cmsUser::isLogged()) {
            if ($currentUserId == $user_id) {
                return true;
            }
            $this->sendMessage(
                array(
                    'title' => '',
                    'msg' => 'Данный аккаунт привязан к другому пользователю. ' .
                        '</br>Вы не можете использовать этот аккаунт',
                    'answerType' => 'error',
                )
            );
            return false;
        }
        return true;
    }


    /**
     * Обработка ответа сервера авторизации
     */
    protected function uloginParseRequest()
    {
        $token = $this->request->get('token');

        if (!$token) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'Не был получен токен uLogin.',
                'answerType' => 'error'
            ));
            return false;
        }

        $s = $this->getUserFromToken($token);

        if (!$s) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'Не удалось получить данные о пользователе с помощью токена.',
                'answerType' => 'error'
            ));
            return false;
        }

        $this->u_data = json_decode($s, true);

        if (!$this->checkTokenError()) {
            return false;
        }

        return $this->u_data;
    }


    /**
     * "Обменивает" токен на пользовательские данные
     */
    protected function getUserFromToken($token = false)
    {
        $response = false;
        if ($token) {

            $data = array(
                'cms' => 'instantcms',
                'version' => cmsCore::getVersion(),
            );

            $request = 'https://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'] .
                '&data=' . base64_encode(json_encode($data));

            if (in_array('curl', get_loaded_extensions())) {
                $c = curl_init($request);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($c);
                curl_close($c);

            } elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
                $response = file_get_contents($request);
            }
        }
        return $response;
    }


    /**
     * Проверка пользовательских данных, полученных по токену
     */
    protected function checkTokenError()
    {
        if (!is_array($this->u_data)) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'Данные о пользователе содержат неверный формат.',
                'answerType' => 'error'
            ));
            return false;
        }

        if (isset($this->u_data['error'])) {
            $strpos = strpos($this->u_data['error'], 'host is not');
            if ($strpos) {
                $this->sendMessage(array(
                    'title' => 'Произошла ошибка при авторизации.',
                    'msg' => '<i>ERROR</i>: адрес хоста не совпадает с оригиналом ' . sub($this->u_data['error'], (int)$strpos + 12),
                    'answerType' => 'error'
                ));
                return false;
            }
            switch ($this->u_data['error']) {
                case 'token expired':
                    $this->sendMessage(array(
                        'title' => 'Произошла ошибка при авторизации.',
                        'msg' => '<i>ERROR</i>: время жизни токена истекло',
                        'answerType' => 'error'
                    ));
                    break;
                case 'invalid token':
                    $this->sendMessage(array(
                        'title' => 'Произошла ошибка при авторизации.',
                        'msg' => '<i>ERROR</i>: неверный токен',
                        'answerType' => 'error'
                    ));
                    break;
                default:
                    $this->sendMessage(array(
                        'title' => 'Произошла ошибка при авторизации.',
                        'msg' => '<i>ERROR</i>: ' . $this->u_data['error'],
                        'answerType' => 'error'
                    ));
            }
            return false;
        }
        if (!isset($this->u_data['identity'])) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'В возвращаемых данных отсутствует переменная <b>identity</b>.',
                'answerType' => 'error'
            ));
            return false;
        }
        if (!isset($this->u_data['email'])) {
            $this->sendMessage(array(
                'title' => 'Произошла ошибка при авторизации.',
                'msg' => 'В возвращаемых данных отсутствует переменная <b>email</b>',
                'answerType' => 'error'
            ));
            return false;
        }
        return true;
    }
}