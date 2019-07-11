<div class="ulogin_form">
<?php
	$this->addCSS( 'assets/icms2ulogin/ulogin.css' );
    $this->addCSS( 'https://ulogin.ru/css/providers.css', false);
?>

	<?php

		$ulogin_accounts = '';
		if (is_array( $networks )) {
			foreach ( $networks as $network => $nwdata ) {
				$ulogin_accounts .= "<div data-ulogin-network='$network' " .
				                    "class=\"ulogin_provider big_provider " . $network . "_big\"></div>";
			}
		}
		$ulogin_accounts = '<div class="ulogin_accounts">' .
		                   $ulogin_accounts .
		                   '</div><div style="clear:both"></div>';
		echo $ulogin_accounts;

	?>

</div>