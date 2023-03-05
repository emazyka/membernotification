<?php

class membernotification_Hooks
{
	public static function hookFrontendContentAfterAdd($params)
	{
		if ($params['success']) {
			self::notifyUsers($params['nodeid'], $params['output']['retUrl']);
		}
	}

	private static function notifyUsers($nodeid, $thread_url)
	{
		vB_Api::instance('membernotification:email')->notifyUsers($nodeid, $thread_url);
	}
}
