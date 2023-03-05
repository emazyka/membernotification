<?php

class membernotification_Product
{

	public $vbMinVersion = '5.2.0';
	public $vbMaxVersion = '5.9.9';

	public static $AutoInstall = true;

	public $hookClasses = array(
		'membernotification_Hooks',
	);
}
