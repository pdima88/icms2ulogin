<?php

namespace pdima88\icms2ulogin;

use cmsBackend;

class backend extends cmsBackend {

	public $useDefaultOptionsAction = true;

//	protected static $group_id;
//
//	public function __construct($request){
//
//		parent::__construct($request);
//
//		$model = cmsCore::getModel($this->name);
//		self::$group_id = $model->getUloginGroupId();
//
//	}

	public function actionIndex(){
		$this->redirectToAction('options');
	}

}