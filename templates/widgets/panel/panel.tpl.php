<div class="ulogin_form">

	<?php $this->addJS( 'https://ulogin.ru/js/ulogin.js', null, false); ?>
	<?php $this->addCSS( 'https://ulogin.ru/css/providers.css', false); ?>

	<?php $this->addJS( 'assets/pdima88/icms2ulogin/ulogin.js' ); ?>
	<?php $this->addCSS( 'assets/pdima88/icms2ulogin/ulogin.css' ); ?>

	<div id="<?php html($id)?>" data-uloginid="<?php html($uloginid);?>" data-ulogin="redirect_uri=<?php html($redirect);?>;callback=<?php html($callback);?>"></div>

</div>